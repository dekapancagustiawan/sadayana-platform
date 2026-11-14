<?php

namespace App\Services\Resident;

use App\Enums\Resident\AssignmentType;
use App\Enums\Resident\HouseResidentStatus;
use App\Models\House;
use App\Models\HouseResident;
use App\Models\OfficerAssignment;
use App\Models\ResidentUserAccount;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * SATU titik kendali "rumah mana yang boleh dilihat user ini" (FR-RES-005,
 * NFR-001 akses silang antar rumah, Dok 07 §5 butir 7).
 *
 * - Pengurus/bendahara melihat semua rumah lewat permission `houses.view`.
 * - Warga melihat rumah tempat profil penghuninya tercatat AKTIF; begitu
 *   periode hunian ditutup, aksesnya hilang dengan sendirinya.
 * - Data per periode (tagihan, meter) wajib dibatasi ke rentang hunian lewat
 *   occupancyPeriods(), supaya penghuni baru tidak melihat transaksi
 *   penghuni lama (FR-RES-004).
 * - Petugas melihat rumah sesuai penugasan aktif (officerHouseIds()).
 */
class HouseAccessService
{
    /**
     * Rumah yang sedang dihuni user (lewat profil penghuni yang terhubung).
     *
     * @return Collection<int, House>
     */
    public function housesFor(User $user): Collection
    {
        return House::query()
            ->whereIn('id', $this->residentHouseIdsQuery($user))
            ->with('block')
            ->orderBy('house_code')
            ->get();
    }

    public function canView(User $user, House $house): bool
    {
        if ($user->can('houses.view')) {
            return true;
        }

        return $this->residentHouseIdsQuery($user)->where('house_id', $house->getKey())->exists();
    }

    /**
     * Rentang hunian aktif/terdahulu user di rumah ini, untuk membatasi data
     * berperiode. end = null berarti masih menghuni.
     *
     * @return Collection<int, array{start: Carbon, end: ?Carbon}>
     */
    public function occupancyPeriods(User $user, House $house): Collection
    {
        return HouseResident::query()
            ->where('house_id', $house->getKey())
            ->whereIn('resident_id', $this->residentIdsQuery($user))
            ->orderBy('start_date')
            ->get()
            ->map(fn (HouseResident $occupancy) => [
                'start' => $occupancy->start_date,
                'end' => $occupancy->end_date,
            ]);
    }

    /**
     * ID rumah yang menjadi tugas petugas pada tanggal tertentu.
     *
     * @return Collection<int, string>
     */
    public function officerHouseIds(User $user, AssignmentType $type, ?string $date = null): Collection
    {
        $assignments = OfficerAssignment::query()
            ->where('user_id', $user->getKey())
            ->where('assignment_type', $type)
            ->current($date)
            ->get(['block_id', 'house_id']);

        if ($assignments->isEmpty()) {
            return collect();
        }

        return House::query()
            ->where(function (Builder $query) use ($assignments) {
                $query->whereIn('id', $assignments->pluck('house_id')->filter())
                    ->orWhereIn('block_id', $assignments->pluck('block_id')->filter());
            })
            ->pluck('id');
    }

    /**
     * Subquery resident_id yang terhubung ke akun user.
     */
    private function residentIdsQuery(User $user): Builder
    {
        return ResidentUserAccount::query()->select('resident_id')->where('user_id', $user->getKey());
    }

    /**
     * Subquery house_id dari hunian AKTIF milik profil penghuni user.
     */
    private function residentHouseIdsQuery(User $user): Builder
    {
        return HouseResident::query()
            ->select('house_id')
            ->where('status', HouseResidentStatus::Active)
            ->whereIn('resident_id', $this->residentIdsQuery($user));
    }
}
