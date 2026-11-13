<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dashboard_notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type'); // NotificationType enum
            $table->string('title');
            $table->text('message');
            $table->string('url')->nullable(); // tautan tujuan saat notifikasi diklik
            $table->string('reference_type')->nullable(); // morph type
            $table->string('reference_id')->nullable();   // morph id (int atau uuid)
            $table->boolean('is_read')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['is_read', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashboard_notifications');
    }
};
