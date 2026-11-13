<?php

namespace App\Enums;

/**
 * Role operasional dari SRS §2 (ROLE-002 s.d. ROLE-006). ROLE-001 Super
 * Admin = RoleType::Admin.
 *
 * Nama di sini hanya dipakai seeder & test untuk membuat role awal beserta
 * izinnya. Kode aplikasi TIDAK boleh memeriksa nama role — selalu periksa
 * permission (`can:`/Gate), supaya pengurus bisa menyesuaikan role dari layar
 * Role Management.
 */
enum CommunityRole: string
{
    case KetuaRt = 'Ketua RT';
    case Bendahara = 'Bendahara';
    case PetugasLapangan = 'Petugas Lapangan';
    case PetugasSampah = 'Petugas Sampah';
    case Warga = 'Warga';

    /**
     * Akun demo (non-production) untuk role ini.
     */
    public function demoEmail(): string
    {
        return match ($this) {
            self::KetuaRt => 'ketua@example.com',
            self::Bendahara => 'bendahara@example.com',
            self::PetugasLapangan => 'petugas.air@example.com',
            self::PetugasSampah => 'petugas.sampah@example.com',
            self::Warga => 'warga@example.com',
        };
    }
}
