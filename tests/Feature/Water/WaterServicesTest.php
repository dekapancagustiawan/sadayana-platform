<?php

namespace Tests\Feature\Water;

use App\Enums\Water\CorrectionStatus;
use App\Enums\Water\MeterReadingStatus;
use App\Enums\Water\ReadingAnomaly;
use App\Enums\Water\WaterMeterStatus;
use App\Models\House;
use App\Models\User;
use App\Models\WaterMeter;
use App\Models\WaterMeterReading;
use App\Models\WaterUsageRecord;
use App\Services\Resident\HouseService;
use App\Services\Water\MeterReadingService;
use App\Services\Water\WaterCustomerService;
use App\Services\Water\WaterTariffService;
use App\Services\Water\WaterUsageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

/**
 * FR-WAT-001..006 dengan angka dari sampel De Java Valley (S-1):
 * tarif Rp5.000/m³ + abodemen Rp5.000.
 */
class WaterServicesTest extends TestCase
{
    use RefreshDatabase;

    private House $house;

    private User $officer;

    private User $validator;

    private MeterReadingService $readings;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('private');
        Carbon::setTestNow('2026-10-05 08:00:00');

        $houses = app(HouseService::class);
        $block = $houses->createBlock(['code' => 'J']);
        $this->house = $houses->createHouse(['block_id' => $block->id, 'house_number' => '24']);
        $this->officer = User::factory()->create();
        $this->validator = User::factory()->create();
        $this->actingAs($this->officer);

        app(WaterTariffService::class)->addPeriod(['effective_from' => '2026-01-01', 'base_fee' => 5000, 'rate_per_unit' => 5000]);
        app(WaterCustomerService::class)->subscribe($this->house, ['start_date' => '2026-01-01', 'meter_number' => 'M-001', 'initial_reading' => 100]);

        $this->readings = app(MeterReadingService::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function record(string $period, string|int $value): WaterMeterReading
    {
        return $this->readings->record($this->house, Carbon::parse($period), $value, UploadedFile::fake()->image('meter.jpg'), $this->officer);
    }

    public function test_first_reading_uses_initial_value_and_sample_tariff(): void
    {
        // Sampel: 109 → 117 = 8 m³ → 40.000 + abodemen 5.000 = 45.000 (perelek di luar air).
        $reading = $this->record('2026-07-01', 109);
        $this->assertSame(MeterReadingStatus::Validated, $reading->status);

        $reading2 = $this->record('2026-08-01', 117);
        $usage = $reading2->usage;

        $this->assertSame('8.000', $usage->usage_volume);
        $this->assertSame('40000.00', $usage->usage_amount);
        $this->assertSame('5000.00', $usage->base_fee);
        $this->assertSame('45000.00', $usage->calculated_amount);
        $this->assertSame($reading->id, $usage->previous_reading_id);
        $this->assertSame('109.000', $usage->calculation_snapshot['previous_value']);
        $this->assertNotNull($reading2->photo_file_id);

        $first = $reading->usage;
        $this->assertSame('9.000', $first->usage_volume); // 109 − 100 (angka awal meter)
    }

    public function test_zero_usage_still_pays_abodemen(): void
    {
        $this->record('2026-08-01', 100);

        $this->assertSame('5000.00', WaterUsageRecord::first()->calculated_amount);
    }

    public function test_decrease_goes_to_review_and_cannot_be_validated_without_correction(): void
    {
        $this->record('2026-08-01', 120);
        $low = $this->record('2026-09-01', 115);

        $this->assertSame(MeterReadingStatus::Pending, $low->status);
        $this->assertSame([ReadingAnomaly::Decrease], $low->anomalyList());
        $this->assertNull($low->usage);

        try {
            $this->readings->validate($low, $this->validator);
            $this->fail('Validasi angka turun harus ditolak.');
        } catch (RuntimeException) {
            $this->assertSame(MeterReadingStatus::Pending, $low->fresh()->status);
        }

        $this->readings->validate($low, $this->validator, 125, 'Salah baca angka');
        $low->refresh();
        $this->assertSame(MeterReadingStatus::Validated, $low->status);
        $this->assertSame('5.000', $low->usage->usage_volume);
        $this->assertSame(1, $low->corrections()->where('status', CorrectionStatus::Approved)->count());
    }

    public function test_spike_is_flagged(): void
    {
        $this->record('2026-05-01', 105);
        $this->record('2026-06-01', 110);
        $this->record('2026-07-01', 115);
        $spike = $this->record('2026-08-01', 160); // 45 m³ vs rata-rata 5

        $this->assertSame(MeterReadingStatus::Pending, $spike->status);
        $this->assertSame([ReadingAnomaly::Spike], $spike->anomalyList());
    }

    public function test_rejected_reading_can_be_recorded_again(): void
    {
        $this->record('2026-08-01', 120);
        $low = $this->record('2026-09-01', 50);
        $this->readings->reject($low, $this->validator, 'Foto buram');

        $again = $this->record('2026-09-01', 126);

        $this->assertSame(MeterReadingStatus::Validated, $again->status);
        $this->assertSame('6.000', $again->usage->usage_volume);
    }

    public function test_duplicate_future_and_backdated_readings_are_refused(): void
    {
        $this->record('2026-08-01', 110);

        foreach (['2026-08-01' => 112, '2026-11-01' => 120, '2026-07-01' => 105] as $period => $value) {
            try {
                $this->record($period, $value);
                $this->fail("Pembacaan {$period} seharusnya ditolak.");
            } catch (RuntimeException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_correction_recalculates_this_and_next_month(): void
    {
        $aug = $this->record('2026-08-01', 110);
        $sep = $this->record('2026-09-01', 120);

        $correction = $this->readings->requestCorrection($aug, 112, 'Salah ketik', $this->officer);
        $this->readings->approveCorrection($correction, $this->validator);

        $this->assertSame('112.000', $aug->fresh()->reading_value);
        $this->assertSame('12.000', $aug->fresh()->usage->usage_volume);
        $this->assertSame('8.000', $sep->fresh()->usage->usage_volume);
        $this->assertSame('110.000', $correction->fresh()->old_value);
    }

    public function test_billed_reading_cannot_be_corrected(): void
    {
        $aug = $this->record('2026-08-01', 110);
        $aug->usage->update(['billed_at' => now()]);

        $this->expectException(RuntimeException::class);
        $this->readings->requestCorrection($aug, 111, 'Telat sadar', $this->officer);
    }

    public function test_meter_replacement_carries_unbilled_volume(): void
    {
        $this->record('2026-08-01', 110);

        $newMeter = app(WaterCustomerService::class)->replaceMeter($this->house, [
            'final_reading' => 113,
            'meter_number' => 'M-002',
            'initial_reading' => 0,
            'replaced_at' => '2026-09-15',
            'old_status' => 'broken',
        ]);

        $this->assertSame('3.000', $newMeter->carried_volume);
        $this->assertSame(WaterMeterStatus::Broken, WaterMeter::where('meter_number', 'M-001')->first()->status);

        $sep = $this->record('2026-09-01', 4);
        $this->assertSame('7.000', $sep->usage->usage_volume); // 3 dari meter lama + 4 di meter baru

        Carbon::setTestNow('2026-11-05');
        $oct = $this->record('2026-10-01', 9);
        $this->assertSame('5.000', $oct->usage->usage_volume); // sisa hanya dibebankan sekali
    }

    public function test_new_tariff_period_applies_only_forward(): void
    {
        $this->record('2026-08-01', 110);
        $tariffs = app(WaterTariffService::class);

        try {
            $tariffs->addPeriod(['effective_from' => '2026-08-01', 'base_fee' => 6000, 'rate_per_unit' => 6000]);
            $this->fail('Tarif untuk bulan yang sudah dihitung harus ditolak.');
        } catch (RuntimeException) {
            $this->addToAssertionCount(1);
        }

        $tariffs->addPeriod(['effective_from' => '2026-09-01', 'base_fee' => 6000, 'rate_per_unit' => 6000]);
        $sep = $this->record('2026-09-01', 112);

        $this->assertSame('18000.00', $sep->usage->calculated_amount); // 6.000 + 2 × 6.000
        // Agustus tetap memakai tarif lama: (110 − 100) × 5.000 + 5.000.
        $this->assertSame('55000.00', WaterUsageRecord::whereDate('usage_period', '2026-08-01')->first()->calculated_amount);
    }

    public function test_minimum_charge(): void
    {
        $period = app(WaterTariffService::class)->addPeriod(['effective_from' => '2026-09-01', 'base_fee' => 0, 'rate_per_unit' => 5000, 'minimum_charge' => 10000]);

        $result = app(WaterTariffService::class)->calculate($period, '1');

        $this->assertSame('10000.00', (string) $result['total']);
        $this->assertTrue($result['minimum_applied']);
    }

    public function test_non_customer_cannot_be_recorded(): void
    {
        $other = app(HouseService::class)->createHouse(['block_id' => $this->house->block_id, 'house_number' => '25']);

        $this->expectException(RuntimeException::class);
        $this->readings->record($other, Carbon::parse('2026-09-01'), 10, UploadedFile::fake()->image('m.jpg'), $this->officer);
    }

    public function test_default_period_is_previous_month_early_in_month(): void
    {
        $this->assertSame('2026-09-01', MeterReadingService::defaultPeriod(Carbon::parse('2026-10-05'))->toDateString());
        $this->assertSame('2026-10-01', MeterReadingService::defaultPeriod(Carbon::parse('2026-10-25'))->toDateString());
    }

    public function test_usage_service_baseline_without_readings(): void
    {
        $meter = app(WaterCustomerService::class)->activeMeter($this->house);
        $baseline = app(WaterUsageService::class)->baseline($meter, Carbon::parse('2026-09-01'));

        $this->assertNull($baseline['reading']);
        $this->assertSame('100.000', (string) $baseline['value']);
    }
}
