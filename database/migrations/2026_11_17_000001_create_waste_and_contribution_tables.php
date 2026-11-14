<?php

use App\Support\Database\Constraints;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 4 — Sampah (FR-WST-001..004, S-3) & Iuran Warga (FR-COM-001..003, S-2).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('waste_service_subscriptions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->community();
            $table->foreignUuid('house_id')->constrained('houses');
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->string('status', 20)->default('active');
            $table->text('notes')->nullable();
            $table->auditFields();
            $table->timestampsTz();

            $table->index(['house_id', 'status']);
        });
        Constraints::check('waste_service_subscriptions', 'waste_subscriptions_status_check', "status IN ('active', 'inactive')");
        Constraints::partialUnique('waste_service_subscriptions', 'waste_subscriptions_house_open_unique', ['house_id'], 'end_date IS NULL');

        Schema::create('waste_fee_periods', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->community();
            $table->date('effective_from');
            $table->date('effective_until')->nullable();
            $table->money('monthly_amount');
            $table->text('notes')->nullable();
            $table->auditFields();
            $table->timestampsTz();
        });
        Constraints::check('waste_fee_periods', 'waste_fee_periods_amount_check', 'monthly_amount >= 0');

        // Pengambilan sampah mingguan per rumah (FR-WST-003).
        Schema::create('waste_service_records', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->community();
            $table->foreignUuid('house_id')->constrained('houses');
            $table->date('service_date');
            $table->string('status', 30);
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->unique(['house_id', 'service_date']);
            $table->index(['service_date', 'status']);
        });
        Constraints::check('waste_service_records', 'waste_service_records_status_check', "status IN ('completed', 'missed', 'not_served', 'rescheduled')");

        Schema::create('contribution_types', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->community();
            $table->string('code', 50);
            $table->string('name', 150);
            $table->text('description')->nullable();
            // routine = bulanan; incidental = sekali tagih pada bulan tertentu
            $table->string('contribution_category', 30)->default('routine');
            // all_houses = semua rumah aktif; assigned = hanya rumah yang ditetapkan
            $table->string('applies_to', 20)->default('all_houses');
            $table->string('status', 20)->default('active');
            $table->auditFields();
            $table->timestampsTz();

            $table->unique(['community_id', 'code']);
        });
        Constraints::check('contribution_types', 'contribution_types_category_check', "contribution_category IN ('routine', 'incidental')");
        Constraints::check('contribution_types', 'contribution_types_applies_check', "applies_to IN ('all_houses', 'assigned')");

        Schema::create('contribution_periods', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('contribution_type_id')->constrained('contribution_types');
            $table->date('effective_from');
            $table->date('effective_until')->nullable();
            $table->money('amount');
            $table->text('notes')->nullable();
            $table->auditFields();
            $table->timestampsTz();
        });
        Constraints::check('contribution_periods', 'contribution_periods_amount_check', 'amount >= 0');

        Schema::create('contribution_assignments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('contribution_type_id')->constrained('contribution_types');
            $table->foreignUuid('house_id')->constrained('houses');
            $table->date('assigned_from');
            $table->date('assigned_until')->nullable();
            $table->money('custom_amount')->nullable();
            $table->auditFields();
            $table->timestampsTz();

            $table->index(['contribution_type_id', 'house_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contribution_assignments');
        Schema::dropIfExists('contribution_periods');
        Schema::dropIfExists('contribution_types');
        Schema::dropIfExists('waste_service_records');
        Schema::dropIfExists('waste_fee_periods');
        Schema::dropIfExists('waste_service_subscriptions');
    }
};
