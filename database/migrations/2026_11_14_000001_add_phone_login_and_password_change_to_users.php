<?php

use App\Support\Phone;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * FR-AUTH-001/003: login dengan email ATAU nomor HP (warga belum tentu punya
 * email) dan kewajiban mengganti password sementara.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Normalisasi nomor yang sudah ada sebelum dikunci unik.
        DB::table('users')->whereNotNull('phone')->orderBy('id')->each(function ($user) {
            DB::table('users')->where('id', $user->id)->update(['phone' => Phone::normalize($user->phone)]);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable()->change();
            $table->boolean('must_change_password')->default(false)->after('password');
            $table->timestampTz('password_changed_at')->nullable()->after('must_change_password');
            $table->unique('phone');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['phone']);
            $table->dropColumn(['must_change_password', 'password_changed_at']);
            $table->string('email')->nullable(false)->change();
        });
    }
};
