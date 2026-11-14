<?php

namespace App\Services\Water;

use App\Enums\Resident\AssignmentType;
use App\Models\House;
use App\Models\StoredFile;
use App\Models\User;
use App\Models\WaterMeterReading;
use App\Models\WaterReadingCorrection;
use App\Services\Resident\HouseAccessService;

/**
 * Siapa yang boleh membuka foto meter (FR-WAT-003, NFR-001), didaftarkan ke
 * FileAccessRegistry untuk kategori `meter_photo`:
 *
 * - pemegang `meter-readings.view` (pengurus/bendahara);
 * - petugas yang SEDANG ditugaskan mencatat air di rumah itu;
 * - warga yang menghuni rumah itu PADA bulan pembacaannya (FR-RES-004 —
 *   penghuni baru tidak melihat foto bulan-bulan penghuni lama).
 *
 * Pengunggah dan Admin sudah diizinkan StoredFilePolicy/Gate::before.
 */
class MeterPhotoAccess
{
    public function __construct(private readonly HouseAccessService $access) {}

    public function __invoke(User $user, StoredFile $file): bool
    {
        if ($user->can('meter-readings.view')) {
            return true;
        }

        $reading = WaterMeterReading::where('photo_file_id', $file->getKey())->first()
            ?? WaterReadingCorrection::where('photo_file_id', $file->getKey())->first()?->reading;

        $house = $reading ? House::find($reading->house_id) : null;
        if (! $house) {
            return false;
        }

        if ($user->can('meter-readings.record')
            && $this->access->officerHouseIds($user, AssignmentType::Water)->contains($house->getKey())) {
            return true;
        }

        $periodStart = $reading->reading_period->copy()->startOfMonth();
        $periodEnd = $reading->reading_period->copy()->endOfMonth();

        return $this->access->occupancyPeriods($user, $house)->contains(
            fn (array $occupancy) => $occupancy['start']->lte($periodEnd)
                && ($occupancy['end'] === null || $occupancy['end']->gte($periodStart)),
        );
    }
}
