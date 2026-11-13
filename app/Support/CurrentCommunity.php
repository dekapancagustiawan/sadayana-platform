<?php

namespace App\Support;

use App\Enums\CommunityStatus;
use App\Models\Community;

/**
 * Komunitas aktif untuk request/proses saat ini.
 *
 * Mode satu komunitas: selalu komunitas dengan kode `community.default_code`,
 * dibuat otomatis bila belum ada. Saat multi-komunitas diterapkan, cukup kelas
 * ini yang berubah (mis. resolve dari user login) — model domain hanya
 * memanggil `CurrentCommunity::id()` lewat trait BelongsToCommunity.
 *
 * Terdaftar sebagai scoped singleton (lihat AppServiceProvider), jadi hasilnya
 * di-cache per request dan direset antar test.
 */
class CurrentCommunity
{
    private ?Community $community = null;

    public function get(): Community
    {
        return $this->community ??= Community::firstOrCreate(
            ['code' => config('community.default_code')],
            [
                'name' => config('community.default_name'),
                'timezone' => config('community.default_timezone'),
                'status' => CommunityStatus::Active,
            ],
        );
    }

    public function id(): string
    {
        return $this->get()->getKey();
    }

    public function set(Community $community): void
    {
        $this->community = $community;
    }
}
