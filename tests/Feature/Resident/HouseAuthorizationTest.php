<?php

namespace Tests\Feature\Resident;

use App\Enums\CommunityRole;
use App\Enums\Resident\AssignmentType;
use App\Models\House;
use App\Models\User;
use App\Services\Resident\HouseAccessService;
use App\Services\Resident\HouseService;
use App\Services\Resident\OfficerAssignmentService;
use App\Services\Resident\ResidencyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Hak akses rumah per role & isolasi antar rumah (FR-RES-005, NFR-001).
 */
class HouseAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private House $houseA;

    private House $houseB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();

        $houses = app(HouseService::class);
        $block = $houses->createBlock(['code' => 'J']);
        $this->houseA = $houses->createHouse(['block_id' => $block->id, 'house_number' => '1']);
        $this->houseB = $houses->createHouse(['block_id' => $block->id, 'house_number' => '2']);
    }

    private function demo(CommunityRole $role): User
    {
        return User::where('email', $role->demoEmail())->firstOrFail();
    }

    /**
     * Warga demo menghuni rumah A.
     */
    private function wargaInHouseA(): User
    {
        $residency = app(ResidencyService::class);
        $warga = $this->demo(CommunityRole::Warga);
        $resident = $residency->createResident(['full_name' => 'Warga A']);
        $residency->linkAccount($resident, $warga);
        $residency->moveIn($this->houseA, $resident, ['start_date' => '2025-01-01']);

        return $warga;
    }

    public function test_ketua_and_bendahara_can_view_but_not_manage(): void
    {
        foreach ([CommunityRole::KetuaRt, CommunityRole::Bendahara] as $role) {
            $user = $this->demo($role);

            $this->actingAs($user)->get(route('houses.index'))->assertOk()->assertSee('J-1');
            $this->actingAs($user)->get(route('houses.show', $this->houseA))->assertOk();
            $this->actingAs($user)->get(route('residents.index'))->assertOk();
            $this->actingAs($user)->get(route('houses.create'))->assertForbidden();
            $this->actingAs($user)->post(route('blocks.store'), ['code' => 'Z', 'status' => 'active'])->assertForbidden();
            $this->actingAs($user)->get(route('houses.import'))->assertForbidden();
        }
    }

    public function test_officers_and_residents_cannot_open_house_admin(): void
    {
        foreach ([CommunityRole::PetugasLapangan, CommunityRole::PetugasSampah, CommunityRole::Warga] as $role) {
            $this->actingAs($this->demo($role))->get(route('houses.index'))->assertForbidden();
            $this->actingAs($this->demo($role))->get(route('houses.show', $this->houseA))->assertForbidden();
        }
    }

    public function test_admin_manages_houses_end_to_end(): void
    {
        $admin = User::where('email', 'admin@example.com')->firstOrFail();

        $this->actingAs($admin)->post(route('houses.store'), [
            'block_id' => $this->houseA->block_id,
            'house_number' => '3a',
            'is_active' => 1,
        ])->assertRedirect();
        $this->assertDatabaseHas('houses', ['house_code' => 'J-3A']);

        $this->actingAs($admin)->post(route('houses.store'), [
            'block_id' => $this->houseA->block_id,
            'house_number' => '1',
        ])->assertSessionHasErrors('house_number');

        $this->actingAs($admin)->post(route('houses.occupancies.store', $this->houseA), [
            'full_name' => 'Pak RT Baru',
            'phone' => '0812 0000 1111',
            'relationship_type' => 'owner',
            'start_date' => today()->toDateString(),
        ])->assertRedirect(route('houses.show', $this->houseA));

        $this->actingAs($admin)->get(route('houses.show', $this->houseA))
            ->assertOk()
            ->assertSee('Pak RT Baru')
            ->assertSee('081200001111');
    }

    public function test_warga_sees_only_own_house(): void
    {
        $warga = $this->wargaInHouseA();

        $this->actingAs($warga)->get(route('my-house.index'))
            ->assertOk()
            ->assertSee('J-1')
            ->assertDontSee('J-2');

        $access = app(HouseAccessService::class);
        $this->assertTrue($access->canView($warga, $this->houseA));
        $this->assertFalse($access->canView($warga, $this->houseB));
    }

    public function test_access_ends_when_occupancy_ends(): void
    {
        $warga = $this->wargaInHouseA();
        $occupancy = $this->houseA->activeOccupancies()->firstOrFail();

        app(ResidencyService::class)->moveOut($occupancy, today());

        $access = app(HouseAccessService::class);
        $this->assertFalse($access->canView($warga, $this->houseA));
        $this->assertCount(0, $access->housesFor($warga));
        // Periode lamanya tetap terbaca untuk membatasi data historis.
        $this->assertCount(1, $access->occupancyPeriods($warga, $this->houseA));
    }

    public function test_new_occupant_does_not_get_previous_occupants_period(): void
    {
        $residency = app(ResidencyService::class);
        $old = $residency->createResident(['full_name' => 'Penghuni Lama']);
        $stay = $residency->moveIn($this->houseA, $old, ['start_date' => '2023-01-01']);
        $residency->moveOut($stay, '2025-12-31');

        $warga = $this->demo(CommunityRole::Warga);
        $new = $residency->createResident(['full_name' => 'Penghuni Baru']);
        $residency->linkAccount($new, $warga);
        $residency->moveIn($this->houseA, $new, ['start_date' => '2026-01-01']);

        $periods = app(HouseAccessService::class)->occupancyPeriods($warga, $this->houseA);

        $this->assertCount(1, $periods);
        $this->assertSame('2026-01-01', $periods->first()['start']->toDateString());
    }

    public function test_officer_houses_follow_current_assignments(): void
    {
        $officer = $this->demo(CommunityRole::PetugasLapangan);
        $service = app(OfficerAssignmentService::class);

        $service->assign([
            'user_id' => $officer->id,
            'assignment_type' => 'water',
            'block_id' => $this->houseA->block_id,
            'assigned_from' => today()->subMonth()->toDateString(),
        ]);

        $access = app(HouseAccessService::class);
        $ids = $access->officerHouseIds($officer, AssignmentType::Water);
        $this->assertEqualsCanonicalizing([$this->houseA->id, $this->houseB->id], $ids->all());
        $this->assertCount(0, $access->officerHouseIds($officer, AssignmentType::Waste));
    }

    public function test_ketua_manages_officer_assignments(): void
    {
        $ketua = $this->demo(CommunityRole::KetuaRt);
        $officer = $this->demo(CommunityRole::PetugasSampah);

        $this->actingAs($ketua)->post(route('officer-assignments.store'), [
            'user_id' => $officer->id,
            'assignment_type' => 'waste',
            'house_id' => $this->houseB->id,
            'assigned_from' => today()->toDateString(),
        ])->assertRedirect(route('officer-assignments.index'));

        $this->actingAs($ketua)->get(route('officer-assignments.index'))->assertOk()->assertSee('J-2');

        $this->actingAs($this->demo(CommunityRole::Bendahara))
            ->get(route('officer-assignments.index'))->assertForbidden();
    }
}
