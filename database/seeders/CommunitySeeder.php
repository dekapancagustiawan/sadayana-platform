<?php

namespace Database\Seeders;

use App\Support\CurrentCommunity;
use Illuminate\Database\Seeder;

/**
 * Memastikan komunitas default (config/community.php) ada.
 */
class CommunitySeeder extends Seeder
{
    public function run(): void
    {
        app(CurrentCommunity::class)->get();
    }
}
