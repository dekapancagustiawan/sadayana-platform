<?php

namespace App\Enums\Resident;

/**
 * Hubungan penghuni dengan rumah.
 */
enum RelationshipType: string
{
    case Owner = 'owner';
    case Tenant = 'tenant';
    case Family = 'family';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Pemilik',
            self::Tenant => 'Penyewa',
            self::Family => 'Anggota keluarga',
            self::Other => 'Lainnya',
        };
    }

    /**
     * Terima nilai enum maupun label Indonesia (untuk import CSV).
     */
    public static function fromInput(?string $value): self
    {
        $value = strtolower(trim((string) $value));

        return match ($value) {
            '', 'owner', 'pemilik' => self::Owner,
            'tenant', 'penyewa', 'sewa', 'kontrak', 'pengontrak' => self::Tenant,
            'family', 'keluarga', 'anggota keluarga' => self::Family,
            default => self::tryFrom($value) ?? self::Other,
        };
    }
}
