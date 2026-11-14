<?php

use App\Support\Database\Constraints;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 2 — Rumah & Penghuni (FR-RES-001..005, Dok 05 §6.3 + amandemen §16.3).
 */
return new class extends Migration
{
    public function up(): void
    {
        // FR-RES-001 / D-01
        Schema::create('blocks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->community();
            $table->string('code', 30);
            $table->string('name', 100)->nullable();
            $table->text('description')->nullable();
            $table->string('status', 20)->default('active');
            $table->auditFields();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->unique(['community_id', 'code']);
        });
        Constraints::check('blocks', 'blocks_status_check', "status IN ('active', 'inactive')");

        // FR-RES-002
        Schema::create('houses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->community();
            $table->foreignUuid('block_id')->constrained('blocks');
            $table->string('house_number', 30);
            $table->string('house_code', 50); // mis. "J-12", dibentuk dari blok + nomor
            $table->text('address')->nullable();
            $table->string('house_type', 30)->nullable();
            $table->decimal('area_size', 12, 2)->nullable();
            $table->string('occupancy_status', 30)->default('vacant');
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->auditFields();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->unique(['block_id', 'house_number']);
            $table->unique(['community_id', 'house_code']);
        });
        Constraints::check('houses', 'houses_occupancy_status_check', "occupancy_status IN ('occupied', 'vacant', 'under_renovation')");

        // FR-RES-003
        Schema::create('residents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->community();
            $table->string('resident_number', 50)->nullable();
            $table->string('full_name', 150);
            $table->string('phone', 20)->nullable();
            $table->string('email', 150)->nullable();
            $table->text('identity_number')->nullable(); // terenkripsi (cast `encrypted`)
            $table->date('birth_date')->nullable();
            $table->string('status', 20)->default('active');
            $table->text('notes')->nullable();
            $table->auditFields();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index(['community_id', 'full_name']);
            $table->index('phone');
        });
        Constraints::check('residents', 'residents_status_check', "status IN ('active', 'inactive', 'moved_out')");

        // FR-RES-004: riwayat hunian, tidak pernah dihapus.
        Schema::create('house_residents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('house_id')->constrained('houses');
            $table->foreignUuid('resident_id')->constrained('residents');
            $table->string('relationship_type', 30)->default('owner');
            $table->boolean('is_primary_contact')->default(false);
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->string('status', 20)->default('active');
            $table->text('end_reason')->nullable();
            $table->auditFields();
            $table->timestampsTz();

            $table->index(['house_id', 'start_date', 'end_date']);
            $table->index(['resident_id', 'status']);
        });
        Constraints::check('house_residents', 'house_residents_dates_check', 'end_date IS NULL OR end_date >= start_date');
        Constraints::check('house_residents', 'house_residents_status_check', "status IN ('active', 'ended')");
        // Satu penghuni hanya sekali aktif di rumah yang sama.
        Constraints::partialUnique('house_residents', 'house_residents_active_unique', ['house_id', 'resident_id'], "status = 'active'");

        // FR-RES-005
        Schema::create('resident_user_accounts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('resident_id')->constrained('residents');
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->unique(['resident_id', 'user_id']);
            $table->index('user_id');
        });

        // D-05: rumah yang ditugaskan ke petugas lapangan / sampah.
        Schema::create('officer_assignments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->community();
            $table->foreignId('user_id')->constrained('users');
            $table->string('assignment_type', 20);
            $table->foreignUuid('block_id')->nullable()->constrained('blocks');
            $table->foreignUuid('house_id')->nullable()->constrained('houses');
            $table->date('assigned_from');
            $table->date('assigned_until')->nullable();
            $table->auditFields();
            $table->timestampsTz();

            $table->index(['user_id', 'assignment_type']);
        });
        Constraints::check('officer_assignments', 'officer_assignments_type_check', "assignment_type IN ('water', 'waste')");
        Constraints::check('officer_assignments', 'officer_assignments_target_check', 'block_id IS NOT NULL OR house_id IS NOT NULL');
        Constraints::check('officer_assignments', 'officer_assignments_dates_check', 'assigned_until IS NULL OR assigned_until >= assigned_from');
    }

    public function down(): void
    {
        Schema::dropIfExists('officer_assignments');
        Schema::dropIfExists('resident_user_accounts');
        Schema::dropIfExists('house_residents');
        Schema::dropIfExists('residents');
        Schema::dropIfExists('houses');
        Schema::dropIfExists('blocks');
    }
};
