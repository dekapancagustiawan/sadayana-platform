<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Model domain memakai UUID, sedangkan `users` dkk. tetap bigint. Kolom
 * `model_id` menampung keduanya sebagai string (bigint lama → "12").
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->string('model_id', 36)->nullable()->change();
        });
    }

    public function down(): void
    {
        // Hanya aman bila belum ada audit log untuk model ber-UUID.
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->unsignedBigInteger('model_id')->nullable()->change();
        });
    }
};
