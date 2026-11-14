<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Pivot penghuni ↔ akun (FR-RES-005). UUID diisi otomatis saat attach().
 */
class ResidentUserAccount extends Pivot
{
    use HasUuids;

    protected $table = 'resident_user_accounts';

    public $incrementing = false;

    protected $keyType = 'string';
}
