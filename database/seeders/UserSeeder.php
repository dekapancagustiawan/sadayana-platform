<?php

namespace Database\Seeders;

use App\Enums\CommunityRole;
use App\Enums\RoleType;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class UserSeeder extends Seeder
{
    /**
     * Development: akun demo admin@example.com & user@example.com (password: "password").
     *
     * Production: hanya akun admin, email & password dari config('app.seed_admin')
     * (SEED_ADMIN_EMAIL / SEED_ADMIN_PASSWORD) — bila password kosong, password acak dibuat dan
     * ditampilkan SEKALI di terminal. Akun demo User tidak dibuat. Seeder ini
     * aman dijalankan ulang: akun yang sudah ada tidak diubah.
     */
    public function run(): void
    {
        if (app()->isProduction()) {
            $this->seedProductionAdmin();

            return;
        }

        $this->seedAccount('admin@example.com', 'Administrator', 'password', RoleType::Admin);
        $this->seedAccount('user@example.com', 'Demo User', 'password', RoleType::User);

        // Satu akun demo per role komunitas (password: "password"). Role-nya
        // dibuat CommunityRoleSeeder; dilewati bila seeder itu belum jalan.
        foreach (CommunityRole::cases() as $role) {
            if (Role::where('name', $role->value)->exists()) {
                $this->seedAccount($role->demoEmail(), 'Demo '.$role->value, 'password', $role);
            }
        }
    }

    private function seedProductionAdmin(): void
    {
        $email = (string) config('app.seed_admin.email');
        $configured = (string) config('app.seed_admin.password');
        $password = $configured !== '' ? $configured : Str::password(20);

        $admin = $this->seedAccount($email, 'Administrator', $password, RoleType::Admin);

        if ($admin->wasRecentlyCreated && $configured === '') {
            $this->command?->warn("Akun admin dibuat: {$email}");
            $this->command?->warn("Password (catat sekarang, tidak ditampilkan lagi): {$password}");
        }
    }

    private function seedAccount(string $email, string $name, string $password, RoleType|CommunityRole $role): User
    {
        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Hash::make($password),
                'status' => 'active',
            ]
        );
        $user->assignRole($role->value);

        return $user;
    }
}
