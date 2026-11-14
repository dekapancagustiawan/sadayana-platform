<?php

namespace Tests\Feature\Resident;

use App\Enums\Resident\HouseResidentStatus;
use App\Enums\Resident\OccupancyStatus;
use App\Enums\Resident\ResidentStatus;
use App\Enums\UserStatus;
use App\Models\House;
use App\Models\Resident;
use App\Models\User;
use App\Services\Resident\HouseService;
use App\Services\Resident\ResidencyService;
use App\Support\Phone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

/**
 * FR-RES-004 / EX-009: riwayat hunian & status rumah.
 */
class ResidencyServiceTest extends TestCase
{
    use RefreshDatabase;

    private ResidencyService $residency;

    private House $house;

    protected function setUp(): void
    {
        parent::setUp();

        $this->residency = app(ResidencyService::class);
        $houses = app(HouseService::class);
        $block = $houses->createBlock(['code' => 'j']);
        $this->house = $houses->createHouse(['block_id' => $block->id, 'house_number' => '12']);
    }

    private function resident(string $name = 'Budi'): Resident
    {
        return $this->residency->createResident(['full_name' => $name, 'phone' => '0812'.random_int(10000000, 99999999)]);
    }

    public function test_house_code_is_built_from_block_and_number(): void
    {
        $this->assertSame('J-12', $this->house->house_code);
        $this->assertSame(OccupancyStatus::Vacant, $this->house->occupancy_status);
    }

    public function test_move_in_marks_house_occupied_and_first_resident_primary(): void
    {
        $budi = $this->resident('Budi');
        $siti = $this->resident('Siti');

        $first = $this->residency->moveIn($this->house, $budi, ['start_date' => '2025-01-01']);
        $second = $this->residency->moveIn($this->house, $siti, ['relationship_type' => 'family']);

        $this->assertTrue($first->fresh()->is_primary_contact);
        $this->assertFalse($second->is_primary_contact);
        $this->assertSame(OccupancyStatus::Occupied, $this->house->fresh()->occupancy_status);
        $this->assertSame('Budi', $this->house->fresh()->primaryResident()->full_name);
        $this->assertDatabaseHas('audit_logs', ['action' => 'residents.moved_in', 'model_id' => $first->id]);
    }

    public function test_same_resident_cannot_move_in_twice(): void
    {
        $budi = $this->resident();
        $this->residency->moveIn($this->house, $budi);

        $this->expectException(RuntimeException::class);
        $this->residency->moveIn($this->house, $budi);
    }

    public function test_move_out_keeps_history_and_hands_over_primary_contact(): void
    {
        $budi = $this->resident('Budi');
        $siti = $this->resident('Siti');
        $budiStay = $this->residency->moveIn($this->house, $budi, ['start_date' => '2024-01-01']);
        $this->residency->moveIn($this->house, $siti, ['start_date' => '2024-06-01']);

        $this->residency->moveOut($budiStay, '2026-01-31', 'Pindah kota');

        $budiStay->refresh();
        $this->assertSame(HouseResidentStatus::Ended, $budiStay->status);
        $this->assertSame('2026-01-31', $budiStay->end_date->toDateString());
        $this->assertSame(ResidentStatus::MovedOut, $budi->fresh()->status);
        $this->assertSame('Siti', $this->house->fresh()->primaryResident()->full_name);
        $this->assertSame(OccupancyStatus::Occupied, $this->house->fresh()->occupancy_status);
        $this->assertSame(2, $this->house->occupancies()->count(), 'riwayat tidak boleh hilang');
    }

    public function test_last_resident_moving_out_makes_house_vacant_and_can_deactivate_account(): void
    {
        $budi = $this->resident();
        $stay = $this->residency->moveIn($this->house, $budi, ['start_date' => '2024-01-01']);
        $user = $this->residency->createAccountFor($budi)['user'];

        $this->residency->moveOut($stay, null, null, deactivateAccounts: true);

        $this->assertSame(OccupancyStatus::Vacant, $this->house->fresh()->occupancy_status);
        $this->assertSame(UserStatus::Inactive, $user->fresh()->status);
    }

    public function test_renovation_status_is_not_overwritten(): void
    {
        $this->house->update(['occupancy_status' => OccupancyStatus::UnderRenovation]);

        $this->residency->moveIn($this->house, $this->resident());

        $this->assertSame(OccupancyStatus::UnderRenovation, $this->house->fresh()->occupancy_status);
    }

    public function test_end_date_before_start_is_rejected(): void
    {
        $stay = $this->residency->moveIn($this->house, $this->resident(), ['start_date' => '2025-05-01']);

        $this->expectException(RuntimeException::class);
        $this->residency->moveOut($stay, '2025-04-30');
    }

    public function test_house_with_history_cannot_be_deleted(): void
    {
        $this->residency->moveIn($this->house, $this->resident());

        $this->expectException(RuntimeException::class);
        app(HouseService::class)->deleteHouse($this->house);
    }

    public function test_changing_block_code_updates_house_codes(): void
    {
        app(HouseService::class)->updateBlock($this->house->block, ['code' => 'k', 'status' => 'active']);

        $this->assertSame('K-12', $this->house->fresh()->house_code);
    }

    public function test_created_account_uses_resident_phone_and_temporary_password(): void
    {
        $this->seed();
        $budi = $this->resident();

        ['user' => $user, 'password' => $password] = $this->residency->createAccountFor($budi);

        $this->assertSame($budi->phone, $user->phone);
        $this->assertTrue($user->must_change_password);
        $this->assertTrue($user->hasRole('Warga'));
        $this->assertTrue($budi->users()->whereKey($user->id)->exists());

        $this->post(route('login.submit'), ['login' => Phone::display($budi->phone), 'password' => $password])
            ->assertRedirect(route('account.password.edit'));
    }

    public function test_account_creation_refuses_duplicate_phone(): void
    {
        $budi = $this->resident();
        User::factory()->create(['phone' => $budi->phone]);

        $this->expectException(RuntimeException::class);
        $this->residency->createAccountFor($budi);
    }

    public function test_identity_number_is_encrypted_at_rest(): void
    {
        $resident = $this->residency->createResident(['full_name' => 'Rahasia', 'identity_number' => '3201010101010001']);

        $raw = DB::table('residents')->where('id', $resident->id)->value('identity_number');

        $this->assertNotSame('3201010101010001', $raw);
        $this->assertSame('3201010101010001', $resident->fresh()->identity_number);
        $this->assertArrayNotHasKey('identity_number', $resident->toArray());
    }
}
