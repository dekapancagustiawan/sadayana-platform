<?php

namespace App\Services\Water;

use App\Enums\Water\CorrectionStatus;
use App\Enums\Water\MeterReadingStatus;
use App\Enums\Water\ReadingAnomaly;
use App\Enums\Water\WaterCustomerStatus;
use App\Models\House;
use App\Models\User;
use App\Models\WaterMeter;
use App\Models\WaterMeterReading;
use App\Models\WaterReadingCorrection;
use App\Models\WaterUsageRecord;
use App\Services\Audit\AuditService;
use App\Services\Files\FileService;
use App\Support\Money;
use Brick\Math\BigDecimal;
use Carbon\CarbonInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Pencatatan, pemeriksaan, dan koreksi meter air (FR-WAT-002/003/006,
 * EX-001/002, Q5).
 *
 * - Foto wajib; bila foto gagal disimpan, pembacaan tidak tersimpan (EX-002).
 * - Pembacaan wajar → langsung valid & pemakaian dihitung. Janggal (angka
 *   turun / melonjak) → `pending`, menunggu pemeriksaan pengurus.
 * - Petugas tidak mengubah angka yang sudah disimpan; perubahan lewat
 *   koreksi beralasan yang disetujui pengurus. Angka yang sudah ditagihkan
 *   tidak bisa dikoreksi diam-diam.
 */
class MeterReadingService
{
    public const PHOTO_CATEGORY = 'meter_photo';

    public function __construct(
        private readonly WaterCustomerService $customers,
        private readonly WaterUsageService $usage,
        private readonly WaterTariffService $tariffs,
        private readonly FileService $files,
        private readonly AuditService $audit,
    ) {}

    /**
     * Bulan default di layar Catat Meter (awal bulan = bulan lalu).
     */
    public static function defaultPeriod(?CarbonInterface $today = null): Carbon
    {
        $today = Carbon::instance($today ?? now());

        return $today->day <= config('water.previous_month_until_day')
            ? $today->copy()->subMonthNoOverflow()->startOfMonth()
            : $today->copy()->startOfMonth();
    }

    /**
     * Catat angka meter sebuah rumah untuk satu bulan pemakaian.
     */
    public function record(House $house, CarbonInterface $period, string|int $value, UploadedFile $photo, User $officer, ?string $notes = null): WaterMeterReading
    {
        $period = Carbon::instance($period)->startOfMonth();

        if ($period->isAfter(now()->startOfMonth())) {
            throw new RuntimeException('Tidak bisa mencatat meter untuk bulan yang belum berjalan.');
        }

        $customer = $this->customers->openCustomer($house);
        if (! $customer || $customer->status === WaterCustomerStatus::Inactive) {
            throw new RuntimeException("Rumah {$house->house_code} bukan pelanggan air aktif.");
        }

        $meter = $this->customers->activeMeter($house);
        if (! $meter) {
            throw new RuntimeException("Rumah {$house->house_code} belum punya meter aktif.");
        }

        if (! $this->tariffs->periodFor($period)) {
            throw new RuntimeException('Tarif air untuk '.$period->translatedFormat('F Y').' belum diatur. Hubungi bendahara.');
        }

        return DB::transaction(function () use ($house, $period, $value, $photo, $officer, $notes, $meter) {
            $meter->newQuery()->whereKey($meter->getKey())->lockForUpdate()->first();

            $existing = WaterMeterReading::where('water_meter_id', $meter->getKey())
                ->whereDate('reading_period', $period->toDateString())
                ->where('status', '!=', MeterReadingStatus::Rejected)
                ->first();
            if ($existing) {
                throw new RuntimeException("Meter {$house->house_code} untuk ".$period->translatedFormat('F Y').' sudah dicatat. Ajukan koreksi bila angkanya salah.');
            }

            $pendingEarlier = WaterMeterReading::where('water_meter_id', $meter->getKey())
                ->where('status', MeterReadingStatus::Pending)
                ->exists();
            if ($pendingEarlier) {
                throw new RuntimeException("Pembacaan {$house->house_code} sebelumnya masih menunggu pemeriksaan pengurus.");
            }

            $later = WaterMeterReading::where('water_meter_id', $meter->getKey())
                ->where('status', MeterReadingStatus::Validated)
                ->whereDate('reading_period', '>', $period->toDateString())
                ->exists();
            if ($later) {
                throw new RuntimeException('Sudah ada pembacaan untuk bulan sesudahnya. Pencatatan mundur tidak diizinkan.');
            }

            $current = Money::quantity($value);
            $anomalies = $this->detectAnomalies($house, $meter, $period, $current);

            $file = $this->files->store($photo, self::PHOTO_CATEGORY);

            $reading = WaterMeterReading::create([
                'water_meter_id' => $meter->getKey(),
                'house_id' => $house->getKey(),
                'reading_period' => $period->toDateString(),
                'reading_value' => (string) $current,
                'reading_date' => now(),
                'photo_file_id' => $file->getKey(),
                'recorded_by' => $officer->getKey(),
                'status' => $anomalies === [] ? MeterReadingStatus::Validated : MeterReadingStatus::Pending,
                'anomalies' => $anomalies === [] ? null : array_map(fn (ReadingAnomaly $a) => $a->value, $anomalies),
                'validated_at' => $anomalies === [] ? now() : null,
                'notes' => $notes,
            ]);

            if ($reading->status === MeterReadingStatus::Validated) {
                $this->usage->calculateFor($reading);
            }

            $this->audit->created('meter_readings.recorded', $reading, ['house_code' => $house->house_code]);

            return $reading;
        });
    }

    /**
     * @return list<ReadingAnomaly>
     */
    public function detectAnomalies(House $house, WaterMeter $meter, CarbonInterface $period, BigDecimal $current, ?string $exceptId = null): array
    {
        $baseline = $this->usage->baseline($meter, $period, $exceptId);
        $volume = $current->minus($baseline['value'])->plus($baseline['carried']);

        if ($current->isLessThan($baseline['value'])) {
            return [ReadingAnomaly::Decrease];
        }

        $average = $this->usage->recentAverage($house->getKey(), $period);
        if ($average !== null) {
            $threshold = $average->multipliedBy(BigDecimal::of((string) config('water.spike_factor')));
            $minDelta = BigDecimal::of((string) config('water.spike_min_volume'));

            if ($volume->isGreaterThan($threshold) && $volume->minus($average)->isGreaterThanOrEqualTo($minDelta)) {
                return [ReadingAnomaly::Spike];
            }
        }

        return [];
    }

    /**
     * Pengurus menyetujui pembacaan janggal. Bisa sekaligus membetulkan
     * angkanya (tercatat sebagai koreksi yang disetujui).
     */
    public function validate(WaterMeterReading $reading, User $validator, string|int|null $correctedValue = null, ?string $notes = null): WaterMeterReading
    {
        return DB::transaction(function () use ($reading, $validator, $correctedValue, $notes) {
            $reading = WaterMeterReading::whereKey($reading->getKey())->lockForUpdate()->firstOrFail();
            $reading->status->assertCanTransitionTo(MeterReadingStatus::Validated);

            if ($correctedValue !== null && $correctedValue !== '' && ! Money::quantity($correctedValue)->isEqualTo(Money::quantity($reading->reading_value))) {
                WaterReadingCorrection::create([
                    'water_meter_reading_id' => $reading->getKey(),
                    'old_value' => (string) Money::quantity($reading->reading_value),
                    'new_value' => (string) Money::quantity($correctedValue),
                    'reason' => $notes ?: 'Dibetulkan saat pemeriksaan.',
                    'status' => CorrectionStatus::Approved,
                    'requested_by' => $validator->getKey(),
                    'decided_by' => $validator->getKey(),
                    'decided_at' => now(),
                ]);
                $reading->reading_value = (string) Money::quantity($correctedValue);
            }

            $old = $reading->only(['status', 'reading_value']);
            $reading->fill([
                'status' => MeterReadingStatus::Validated,
                'validated_by' => $validator->getKey(),
                'validated_at' => now(),
            ]);
            $reading->save();

            // Melempar bila angka masih lebih kecil dari bulan lalu.
            $this->usage->calculateFor($reading);

            $this->audit->changed('meter_readings.validated', $reading, $notes, $old);

            return $reading;
        });
    }

    /**
     * Tolak pembacaan janggal; petugas mencatat ulang bulan yang sama.
     */
    public function reject(WaterMeterReading $reading, User $validator, string $reason): WaterMeterReading
    {
        return DB::transaction(function () use ($reading, $validator, $reason) {
            $reading = WaterMeterReading::whereKey($reading->getKey())->lockForUpdate()->firstOrFail();
            $reading->status->assertCanTransitionTo(MeterReadingStatus::Rejected);

            $old = $reading->only(['status']);
            $reading->fill([
                'status' => MeterReadingStatus::Rejected,
                'validated_by' => $validator->getKey(),
                'validated_at' => now(),
                'rejection_reason' => $reason,
            ]);
            $reading->save();
            $this->audit->changed('meter_readings.rejected', $reading, $reason, $old);

            return $reading;
        });
    }

    /**
     * Ajukan koreksi angka pembacaan yang sudah valid (FR-WAT-006).
     */
    public function requestCorrection(WaterMeterReading $reading, string|int $newValue, string $reason, User $requester, ?UploadedFile $photo = null): WaterReadingCorrection
    {
        if ($reading->status !== MeterReadingStatus::Validated) {
            throw new RuntimeException('Hanya pembacaan yang sudah valid yang bisa dikoreksi. Pembacaan yang menunggu pemeriksaan cukup divalidasi dengan angka yang benar.');
        }

        if ($reading->corrections()->where('status', CorrectionStatus::Pending)->exists()) {
            throw new RuntimeException('Masih ada koreksi untuk pembacaan ini yang menunggu persetujuan.');
        }

        $this->assertCorrectable($reading);

        return DB::transaction(function () use ($reading, $newValue, $reason, $requester, $photo) {
            $file = $photo ? $this->files->store($photo, self::PHOTO_CATEGORY) : null;

            $correction = WaterReadingCorrection::create([
                'water_meter_reading_id' => $reading->getKey(),
                'old_value' => (string) Money::quantity($reading->reading_value),
                'new_value' => (string) Money::quantity($newValue),
                'reason' => $reason,
                'photo_file_id' => $file?->getKey(),
                'status' => CorrectionStatus::Pending,
                'requested_by' => $requester->getKey(),
            ]);
            $this->audit->created('meter_readings.correction_requested', $correction);

            return $correction;
        });
    }

    /**
     * Setujui koreksi: angka diperbarui, pemakaian bulan ini dan bulan
     * berikutnya (yang memakai angka ini sebagai pembanding) dihitung ulang.
     */
    public function approveCorrection(WaterReadingCorrection $correction, User $decider, ?string $notes = null): WaterReadingCorrection
    {
        return DB::transaction(function () use ($correction, $decider, $notes) {
            $correction = WaterReadingCorrection::whereKey($correction->getKey())->lockForUpdate()->firstOrFail();
            $correction->status->assertCanTransitionTo(CorrectionStatus::Approved);

            $reading = WaterMeterReading::whereKey($correction->water_meter_reading_id)->lockForUpdate()->firstOrFail();
            $this->assertCorrectable($reading);

            $newValue = Money::quantity($correction->new_value);
            $baseline = $this->usage->baseline($reading->meter, $reading->reading_period, $reading->getKey());
            if ($newValue->isLessThan($baseline['value'])) {
                throw new RuntimeException("Angka koreksi ({$newValue}) lebih kecil dari angka bulan sebelumnya ({$baseline['value']}).");
            }

            $next = $this->usage->nextReading($reading);
            if ($next && Money::quantity($next->reading_value)->isLessThan($newValue)) {
                throw new RuntimeException("Angka koreksi ({$newValue}) lebih besar dari angka bulan berikutnya ({$next->reading_value}).");
            }

            $old = $reading->only(['reading_value']);
            $reading->reading_value = (string) $newValue;
            $reading->save();

            $this->usage->calculateFor($reading);
            if ($next) {
                $this->usage->calculateFor($next);
            }

            $correction->fill([
                'status' => CorrectionStatus::Approved,
                'decided_by' => $decider->getKey(),
                'decided_at' => now(),
                'decision_notes' => $notes,
            ])->save();

            $this->audit->changed('meter_readings.corrected', $reading, $correction->reason, $old, [
                'correction_id' => $correction->getKey(),
            ]);

            return $correction;
        });
    }

    public function rejectCorrection(WaterReadingCorrection $correction, User $decider, string $notes): WaterReadingCorrection
    {
        return DB::transaction(function () use ($correction, $decider, $notes) {
            $correction = WaterReadingCorrection::whereKey($correction->getKey())->lockForUpdate()->firstOrFail();
            $correction->status->assertCanTransitionTo(CorrectionStatus::Rejected);

            $correction->fill([
                'status' => CorrectionStatus::Rejected,
                'decided_by' => $decider->getKey(),
                'decided_at' => now(),
                'decision_notes' => $notes,
            ])->save();
            $this->audit->record('meter_readings.correction_rejected', $correction, ['reason' => $notes]);

            return $correction;
        });
    }

    /**
     * Angka yang sudah masuk tagihan — atau menjadi pembanding tagihan bulan
     * berikutnya — tidak boleh berubah diam-diam (FR-WAT-006).
     */
    private function assertCorrectable(WaterMeterReading $reading): void
    {
        $billed = WaterUsageRecord::query()
            ->whereNotNull('billed_at')
            ->where(fn ($q) => $q->where('current_reading_id', $reading->getKey())
                ->orWhere('previous_reading_id', $reading->getKey()))
            ->exists();

        if ($billed) {
            throw new RuntimeException('Angka ini sudah dipakai di tagihan yang terbit, sehingga tidak bisa dikoreksi langsung. Selesaikan lewat penyesuaian tagihan.');
        }
    }
}
