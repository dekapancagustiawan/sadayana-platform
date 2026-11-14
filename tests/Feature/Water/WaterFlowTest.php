<?php

namespace Tests\Feature\Water;

use App\Enums\CommunityRole;
use App\Enums\Water\MeterReadingStatus;
use App\Models\House;
use App\Models\User;
use App\Models\WaterMeterReading;
use App\Services\Resident\HouseService;
use App\Services\Resident\OfficerAssignmentService;
use App\Services\Resident\ResidencyService;
use App\Services\Water\WaterCustomerService;
use App\Services\Water\WaterTariffService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Alur air lewat HTTP per role: petugas mencatat dari HP, pengurus
 * memeriksa, bendahara mengatur tarif, warga melihat pemakaian & foto.
 */
class WaterFlowTest extends TestCase
{
    use RefreshDatabase;

    private House $house;

    private House $otherHouse;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('private');
        Carbon::setTestNow('2026-10-05 08:00:00');
        $this->seed();

        $houses = app(HouseService::class);
        $block = $houses->createBlock(['code' => 'J']);
        $this->house = $houses->createHouse(['block_id' => $block->id, 'house_number' => '24']);
        $this->otherHouse = $houses->createHouse(['block_id' => $block->id, 'house_number' => '25']);

        app(WaterTariffService::class)->addPeriod(['effective_from' => '2026-01-01', 'base_fee' => 5000, 'rate_per_unit' => 5000]);
        app(WaterCustomerService::class)->subscribe($this->house, ['start_date' => '2026-01-01', 'initial_reading' => 100]);
        app(WaterCustomerService::class)->subscribe($this->otherHouse, ['start_date' => '2026-01-01', 'initial_reading' => 0]);

        app(OfficerAssignmentService::class)->assign([
            'user_id' => $this->demo(CommunityRole::PetugasLapangan)->id,
            'assignment_type' => 'water',
            'house_id' => $this->house->id,
            'assigned_from' => '2026-01-01',
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function demo(CommunityRole $role): User
    {
        return User::where('email', $role->demoEmail())->firstOrFail();
    }

    private function recordAsOfficer(House $house, string|int $value, ?UploadedFile $photo = null)
    {
        return $this->actingAs($this->demo(CommunityRole::PetugasLapangan))
            ->post(route('meter-recording.store', $house), [
                'period' => '2026-09',
                'reading_value' => $value,
                'photo' => $photo ?? UploadedFile::fake()->image('meter.jpg'),
            ]);
    }

    public function test_officer_sees_only_assigned_houses_and_records_with_photo(): void
    {
        $officer = $this->demo(CommunityRole::PetugasLapangan);

        $this->actingAs($officer)->get(route('meter-recording.index'))
            ->assertOk()
            ->assertSee('J-24')
            ->assertDontSee('J-25')
            ->assertSee('September 2026');

        $this->actingAs($officer)->get(route('meter-recording.create', ['house' => $this->house, 'period' => '2026-09']))
            ->assertOk()
            ->assertSee('Angka sebelumnya');

        $this->recordAsOfficer($this->house, 108)
            ->assertRedirect(route('meter-recording.index', ['period' => '2026-09']))
            ->assertSessionHas('success');

        $reading = WaterMeterReading::firstOrFail();
        $this->assertSame('108.000', $reading->reading_value);
        $this->assertSame('8.000', $reading->usage->usage_volume);

        $this->actingAs($officer)->get(route('meter-recording.index', ['period' => '2026-09']))->assertSee('1 / 1 rumah tercatat');
    }

    public function test_photo_is_mandatory(): void
    {
        $this->actingAs($this->demo(CommunityRole::PetugasLapangan))
            ->post(route('meter-recording.store', $this->house), ['period' => '2026-09', 'reading_value' => 108])
            ->assertSessionHasErrors('photo');

        $this->assertSame(0, WaterMeterReading::count());
    }

    public function test_officer_cannot_record_unassigned_house(): void
    {
        $this->recordAsOfficer($this->otherHouse, 5)->assertForbidden();
        $this->actingAs($this->demo(CommunityRole::PetugasLapangan))
            ->get(route('meter-recording.create', $this->otherHouse))->assertForbidden();
    }

    public function test_other_roles_cannot_record(): void
    {
        foreach ([CommunityRole::Warga, CommunityRole::PetugasSampah, CommunityRole::Bendahara] as $role) {
            $this->actingAs($this->demo($role))->get(route('meter-recording.index'))->assertForbidden();
        }
    }

    public function test_pending_reading_is_validated_by_ketua(): void
    {
        $this->recordAsOfficer($this->house, 90)->assertSessionHas('warning');
        $reading = WaterMeterReading::firstOrFail();
        $this->assertSame(MeterReadingStatus::Pending, $reading->status);

        $ketua = $this->demo(CommunityRole::KetuaRt);
        $this->actingAs($ketua)->get(route('meter-readings.index', ['period' => '2026-09']))
            ->assertOk()->assertSee('J-24')->assertSee('Perlu tindakan');
        $this->actingAs($ketua)->get(route('meter-readings.show', $reading))->assertOk()->assertSee('Periksa pembacaan');

        $this->actingAs($ketua)->post(route('meter-readings.validate', $reading), [
            'corrected_value' => 109,
            'notes' => 'Petugas salah baca',
        ])->assertSessionHas('success');

        $this->assertSame(MeterReadingStatus::Validated, $reading->fresh()->status);
        $this->assertSame('9.000', $reading->fresh()->usage->usage_volume);
    }

    public function test_officer_requests_correction_and_ketua_approves(): void
    {
        $this->recordAsOfficer($this->house, 108);
        $reading = WaterMeterReading::firstOrFail();

        $this->actingAs($this->demo(CommunityRole::PetugasLapangan))
            ->post(route('meter-readings.corrections.store', $reading), ['new_value' => 110, 'reason' => 'Salah ketik'])
            ->assertSessionHas('success');

        $correction = $reading->corrections()->firstOrFail();
        $this->actingAs($this->demo(CommunityRole::KetuaRt))
            ->post(route('reading-corrections.approve', $correction))
            ->assertSessionHas('success');

        $this->assertSame('10.000', $reading->fresh()->usage->usage_volume);
    }

    public function test_tariff_is_managed_by_bendahara_only(): void
    {
        $payload = ['effective_from' => '2026-12-01', 'base_fee' => 6000, 'rate_per_unit' => 5500];

        $this->actingAs($this->demo(CommunityRole::KetuaRt))->get(route('water-tariffs.index'))->assertOk();
        $this->actingAs($this->demo(CommunityRole::KetuaRt))->post(route('water-tariffs.periods.store'), $payload)->assertForbidden();

        $this->actingAs($this->demo(CommunityRole::Bendahara))->post(route('water-tariffs.periods.store'), $payload)
            ->assertSessionHas('success');
        $this->actingAs($this->demo(CommunityRole::Bendahara))->get(route('water-tariffs.index'))
            ->assertSee('01/12/2026')->assertSee('Rp5.500');
    }

    public function test_bendahara_subscribes_house_from_house_page(): void
    {
        $houses = app(HouseService::class);
        $new = $houses->createHouse(['block_id' => $this->house->block_id, 'house_number' => '30']);
        $bendahara = $this->demo(CommunityRole::Bendahara);

        $this->actingAs($bendahara)->get(route('houses.show', $new))->assertOk()->assertSee('Daftarkan pelanggan air');

        $this->actingAs($bendahara)->post(route('water-customers.subscribe', $new), [
            'start_date' => '2026-10-01', 'initial_reading' => 6, 'meter_number' => 'M-30',
        ])->assertSessionHas('success');

        $this->actingAs($bendahara)->get(route('water-customers.index'))->assertSee('AIR-J-30')->assertSee('M-30');
    }

    public function test_resident_sees_own_usage_and_photo_only_for_own_period(): void
    {
        $this->recordAsOfficer($this->house, 108);
        $reading = WaterMeterReading::firstOrFail();

        $residency = app(ResidencyService::class);
        $warga = $this->demo(CommunityRole::Warga);
        $resident = $residency->createResident(['full_name' => 'Warga J-24']);
        $residency->linkAccount($resident, $warga);
        $stay = $residency->moveIn($this->house, $resident, ['start_date' => '2025-01-01']);

        $this->actingAs($warga)->get(route('my-house.index'))
            ->assertOk()->assertSee('Pemakaian air')->assertSee('Rp45.000');
        $this->actingAs($warga)->get(route('files.show', $reading->photo_file_id))->assertOk();

        // Penghuni berikutnya tidak melihat foto bulan penghuni lama.
        $residency->moveOut($stay, '2026-09-30');
        $nextUser = User::factory()->create();
        $next = $residency->createResident(['full_name' => 'Penghuni Baru']);
        $residency->linkAccount($next, $nextUser);
        $residency->moveIn($this->house, $next, ['start_date' => '2026-10-01']);

        $this->actingAs($nextUser)->get(route('files.show', $reading->photo_file_id))->assertForbidden();

        // Warga rumah lain juga tidak boleh.
        $this->actingAs(User::factory()->create())->get(route('files.show', $reading->photo_file_id))->assertForbidden();
    }

    public function test_all_water_pages_render_for_admin(): void
    {
        $this->recordAsOfficer($this->house, 108);
        $reading = WaterMeterReading::firstOrFail();
        $admin = User::where('email', 'admin@example.com')->firstOrFail();

        foreach ([
            route('meter-recording.index'),
            route('meter-recording.create', ['house' => $this->house, 'period' => '2026-09']),
            route('meter-readings.index', ['period' => '2026-09']),
            route('meter-readings.show', $reading),
            route('water-customers.index'),
            route('water-customers.index', ['all' => 1, 'status' => 'active']),
            route('water-tariffs.index'),
            route('houses.show', $this->house),
        ] as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }
    }
}
