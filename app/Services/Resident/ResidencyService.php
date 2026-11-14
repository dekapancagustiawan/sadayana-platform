<?php

namespace App\Services\Resident;

use App\Enums\Resident\HouseResidentStatus;
use App\Enums\Resident\OccupancyStatus;
use App\Enums\Resident\RelationshipType;
use App\Enums\Resident\ResidentStatus;
use App\Enums\UserStatus;
use App\Models\House;
use App\Models\HouseResident;
use App\Models\Resident;
use App\Models\User;
use App\Services\Audit\AuditService;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;
use Spatie\Permission\Models\Role;

/**
 * Penghuni, riwayat hunian, dan akun warga (FR-RES-003..005, EX-009).
 *
 * Aturan inti:
 * - Riwayat hunian tidak pernah dihapus; pindah keluar = periode ditutup.
 * - Akses warga ke rumah diturunkan dari periode hunian AKTIF
 *   (HouseAccessService), jadi menutup periode otomatis mencabut akses.
 * - occupancy_status rumah disinkronkan dari jumlah hunian aktif, kecuali
 *   rumah yang sedang ditandai renovasi.
 */
class ResidencyService
{
    public function __construct(private readonly AuditService $audit) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function createResident(array $data): Resident
    {
        return DB::transaction(function () use ($data) {
            $resident = Resident::create([
                'status' => ResidentStatus::Active->value,
                ...$data,
            ]);
            $this->audit->created('residents.created', $resident);

            return $resident;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateResident(Resident $resident, array $data): Resident
    {
        return DB::transaction(function () use ($resident, $data) {
            // Nomor identitas kosong pada form = tidak diubah (tidak pernah
            // dirender balik ke form).
            if (array_key_exists('identity_number', $data) && blank($data['identity_number'])) {
                unset($data['identity_number']);
            }

            $resident->fill($data);
            $this->audit->changed('residents.updated', $resident);
            $resident->save();

            return $resident;
        });
    }

    /**
     * Penghuni mulai menghuni rumah (pindah masuk).
     *
     * @param  array{relationship_type?: string, is_primary_contact?: bool, start_date?: string}  $data
     */
    public function moveIn(House $house, Resident $resident, array $data = []): HouseResident
    {
        return DB::transaction(function () use ($house, $resident, $data) {
            House::whereKey($house->getKey())->lockForUpdate()->first();

            $alreadyActive = HouseResident::where('house_id', $house->getKey())
                ->where('resident_id', $resident->getKey())
                ->where('status', HouseResidentStatus::Active)
                ->exists();

            if ($alreadyActive) {
                throw new RuntimeException("{$resident->full_name} sudah tercatat menghuni rumah {$house->house_code}.");
            }

            $isPrimary = (bool) ($data['is_primary_contact'] ?? false)
                || ! $house->activeOccupancies()->exists();

            if ($isPrimary) {
                $house->activeOccupancies()->update(['is_primary_contact' => false]);
            }

            $occupancy = HouseResident::create([
                'house_id' => $house->getKey(),
                'resident_id' => $resident->getKey(),
                'relationship_type' => $data['relationship_type'] ?? RelationshipType::Owner->value,
                'is_primary_contact' => $isPrimary,
                'start_date' => $data['start_date'] ?? today()->toDateString(),
                'status' => HouseResidentStatus::Active,
            ]);

            if ($resident->status !== ResidentStatus::Active) {
                $resident->update(['status' => ResidentStatus::Active]);
            }

            $this->syncOccupancyStatus($house);
            $this->audit->created('residents.moved_in', $occupancy, [
                'house_code' => $house->house_code,
                'resident' => $resident->full_name,
            ]);

            return $occupancy;
        });
    }

    /**
     * Tutup periode hunian (pindah keluar). Bila penghuni tidak lagi punya
     * hunian aktif, statusnya menjadi `moved_out` dan — opsional — akun
     * warganya dinonaktifkan.
     */
    public function moveOut(HouseResident $occupancy, CarbonInterface|string|null $endDate = null, ?string $reason = null, bool $deactivateAccounts = false): HouseResident
    {
        return DB::transaction(function () use ($occupancy, $endDate, $reason, $deactivateAccounts) {
            $occupancy = HouseResident::whereKey($occupancy->getKey())->lockForUpdate()->firstOrFail();

            if (! $occupancy->isActive()) {
                throw new RuntimeException('Periode hunian ini sudah ditutup.');
            }

            $end = $endDate === null ? today() : Carbon::parse($endDate)->startOfDay();

            if ($end->lt($occupancy->start_date)) {
                throw new RuntimeException('Tanggal keluar tidak boleh sebelum tanggal mulai menghuni ('.$occupancy->start_date->format('d/m/Y').').');
            }

            $old = $occupancy->only(['status', 'end_date']);
            $occupancy->update([
                'end_date' => $end->toDateString(),
                'status' => HouseResidentStatus::Ended,
                'end_reason' => $reason,
                'is_primary_contact' => false,
            ]);

            $house = $occupancy->house;
            $resident = $occupancy->resident;

            // Kontak utama pindah ke penghuni aktif berikutnya (bila ada).
            $next = $house->activeOccupancies()->oldest('start_date')->first();
            if ($next && ! $house->activeOccupancies()->where('is_primary_contact', true)->exists()) {
                $next->update(['is_primary_contact' => true]);
            }

            if (! $resident->activeOccupancies()->exists()) {
                $resident->update(['status' => ResidentStatus::MovedOut]);

                if ($deactivateAccounts) {
                    $resident->users()->each(fn (User $user) => $user->update(['status' => UserStatus::Inactive]));
                }
            }

            $this->syncOccupancyStatus($house);
            $this->audit->changed('residents.moved_out', $occupancy, $reason, $old, [
                'house_code' => $house->house_code,
                'resident' => $resident->full_name,
                'accounts_deactivated' => $deactivateAccounts,
            ]);

            return $occupancy;
        });
    }

    /**
     * Hubungkan akun yang sudah ada ke profil penghuni.
     */
    public function linkAccount(Resident $resident, User $user): void
    {
        DB::transaction(function () use ($resident, $user) {
            if ($resident->users()->whereKey($user->getKey())->exists()) {
                throw new RuntimeException('Akun tersebut sudah terhubung ke penghuni ini.');
            }

            $resident->users()->attach($user->getKey(), ['created_by' => Auth::id()]);
            $this->audit->record('residents.account_linked', $resident, ['user_id' => $user->getKey()]);
        });
    }

    public function unlinkAccount(Resident $resident, User $user): void
    {
        DB::transaction(function () use ($resident, $user) {
            $resident->users()->detach($user->getKey());
            $this->audit->record('residents.account_unlinked', $resident, ['user_id' => $user->getKey()]);
        });
    }

    /**
     * Buat akun warga dari data penghuni (login dengan email/nomor HP,
     * password sementara wajib diganti — FR-AUTH-003).
     *
     * @return array{user: User, password: string}
     */
    public function createAccountFor(Resident $resident, ?string $password = null): array
    {
        if (blank($resident->phone) && blank($resident->email)) {
            throw new RuntimeException('Isi nomor HP atau email penghuni terlebih dahulu — salah satunya dipakai untuk login.');
        }

        if ($resident->email && User::where('email', $resident->email)->exists()) {
            throw new RuntimeException('Email penghuni sudah dipakai akun lain. Hubungkan akun yang ada, atau ubah email penghuni.');
        }

        if ($resident->phone && User::where('phone', $resident->phone)->exists()) {
            throw new RuntimeException('Nomor HP penghuni sudah dipakai akun lain. Hubungkan akun yang ada, atau ubah nomor HP penghuni.');
        }

        $password ??= Str::password(10, symbols: false);

        return DB::transaction(function () use ($resident, $password) {
            $user = User::create([
                'name' => $resident->full_name,
                'email' => $resident->email,
                'phone' => $resident->phone,
                'password' => Hash::make($password),
                'status' => UserStatus::Active,
                'must_change_password' => true,
            ]);

            $role = Role::where('name', config('community.resident_role'))->first();
            if ($role) {
                $user->assignRole($role);
            }

            $resident->users()->attach($user->getKey(), ['created_by' => Auth::id()]);
            $this->audit->record('residents.account_created', $resident, ['user_id' => $user->getKey()]);

            return ['user' => $user, 'password' => $password];
        });
    }

    /**
     * occupied ↔ vacant mengikuti hunian aktif; renovasi tidak disentuh.
     */
    public function syncOccupancyStatus(House $house): void
    {
        $house->refresh();

        if ($house->occupancy_status === OccupancyStatus::UnderRenovation) {
            return;
        }

        $status = $house->activeOccupancies()->exists() ? OccupancyStatus::Occupied : OccupancyStatus::Vacant;

        if ($house->occupancy_status !== $status) {
            $house->update(['occupancy_status' => $status]);
        }
    }
}
