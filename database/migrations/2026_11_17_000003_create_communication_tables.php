<?php

use App\Support\Database\Constraints;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 8 — Pengumuman, pengaduan (termasuk "sampah belum diambil", S-3), dan
 * notifikasi per user (tabel database notification bawaan Laravel, D-08).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('announcements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->community();
            $table->string('title', 150);
            $table->text('content');
            $table->timestampTz('publish_at');
            $table->timestampTz('expires_at')->nullable();
            $table->string('status', 20)->default('draft');
            $table->auditFields();
            $table->timestampsTz();

            $table->index(['status', 'publish_at']);
        });
        Constraints::check('announcements', 'announcements_status_check', "status IN ('draft', 'published', 'archived')");

        Schema::create('complaints', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->community();
            $table->string('complaint_number', 50);
            $table->foreignUuid('house_id')->nullable()->constrained('houses');
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('category', 50);
            $table->text('description');
            $table->foreignUuid('attachment_file_id')->nullable()->constrained('files');
            $table->string('status', 30)->default('submitted');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->text('resolution_notes')->nullable();
            $table->timestampTz('resolved_at')->nullable();
            $table->timestampsTz();

            $table->unique(['community_id', 'complaint_number']);
            $table->index(['status', 'created_at']);
        });
        Constraints::check('complaints', 'complaints_status_check', "status IN ('submitted', 'in_progress', 'resolved', 'rejected', 'closed')");

        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->jsonb('data');
            $table->timestampTz('read_at')->nullable();
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('complaints');
        Schema::dropIfExists('announcements');
    }
};
