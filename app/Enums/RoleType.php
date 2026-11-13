<?php

namespace App\Enums;

/**
 * Role sistem yang dibuat RoleSeeder.
 *
 * Role lain boleh dibuat bebas lewat layar Role Management; enum ini hanya
 * untuk role yang dirujuk dari kode (Gate::before, seeder, test).
 */
enum RoleType: string
{
    case Admin = 'Admin';
    case User = 'User';
}
