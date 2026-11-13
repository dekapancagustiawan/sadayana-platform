<?php

namespace App\Models;

use App\Enums\CommunityStatus;
use App\Traits\Blameable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Community extends Model
{
    use Blameable, HasUuids;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'code',
        'name',
        'address',
        'timezone',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'status' => CommunityStatus::class,
        ];
    }
}
