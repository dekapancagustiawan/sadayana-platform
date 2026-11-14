<?php

namespace App\Models;

use App\Enums\Resident\HouseResidentStatus;
use App\Enums\Resident\ResidentStatus;
use App\Support\Phone;
use App\Traits\BelongsToCommunity;
use App\Traits\Blameable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * Profil penghuni (FR-RES-003). Terpisah dari `users`: penghuni belum tentu
 * punya akun aplikasi. Nomor identitas terenkripsi dan hanya ditampilkan ke
 * pemegang izin `residents.view-identity`.
 */
class Resident extends Model
{
    use BelongsToCommunity, Blameable, HasUuids, SoftDeletes;

    /** @var list<string> */
    protected $fillable = [
        'resident_number',
        'full_name',
        'phone',
        'email',
        'identity_number',
        'birth_date',
        'status',
        'notes',
    ];

    /** @var list<string> */
    protected $hidden = ['identity_number'];

    protected function casts(): array
    {
        return [
            'status' => ResidentStatus::class,
            'identity_number' => 'encrypted',
            'birth_date' => 'date',
        ];
    }

    protected function phone(): Attribute
    {
        return Attribute::make(set: fn (?string $value) => Phone::normalize($value));
    }

    protected function email(): Attribute
    {
        return Attribute::make(set: fn (?string $value) => filled($value) ? Str::lower(trim($value)) : null);
    }

    /**
     * @return HasMany<HouseResident, $this>
     */
    public function occupancies(): HasMany
    {
        return $this->hasMany(HouseResident::class)->latest('start_date');
    }

    /**
     * @return HasMany<HouseResident, $this>
     */
    public function activeOccupancies(): HasMany
    {
        return $this->hasMany(HouseResident::class)->where('status', HouseResidentStatus::Active);
    }

    /**
     * Akun aplikasi yang mewakili penghuni ini (tabel resident_user_accounts).
     *
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'resident_user_accounts')
            ->using(ResidentUserAccount::class)
            ->withPivot('id', 'created_by')
            ->withTimestamps();
    }

    /**
     * Nomor HP tampilan: 62812… → 0812…
     */
    public function displayPhone(): ?string
    {
        return Phone::display($this->phone);
    }
}
