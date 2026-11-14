<?php

use App\Support\Database\Constraints;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 3 — Air (FR-WAT-001..006, Dok 05 §6.4 + amandemen §16.3).
 *
 * - water_customers   : langganan air per rumah (tidak semua rumah ikut, S-3)
 * - water_meters      : meteran fisik di rumah, bisa diganti (D-02)
 * - water_tariffs / water_tariff_periods : tarif flat berperiode (Q4, S-1)
 * - water_meter_readings : pembacaan bulanan + foto (FR-WAT-002/003)
 * - water_reading_corrections : koreksi beralasan (FR-WAT-006)
 * - water_usage_records : hasil hitung pemakaian + snapshot (FR-WAT-004)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('water_customers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->community();
            $table->foreignUuid('house_id')->constrained('houses');
            $table->string('customer_number', 50);
            // active = ditagih; suspended = diputus sementara (S-7); inactive = berhenti
            $table->string('status', 20)->default('active');
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->text('notes')->nullable();
            $table->auditFields();
            $table->timestampsTz();

            $table->unique(['community_id', 'customer_number']);
            $table->index(['house_id', 'status']);
        });
        Constraints::check('water_customers', 'water_customers_status_check', "status IN ('active', 'suspended', 'inactive')");
        Constraints::check('water_customers', 'water_customers_dates_check', 'end_date IS NULL OR end_date >= start_date');
        // Satu langganan berjalan per rumah.
        Constraints::partialUnique('water_customers', 'water_customers_house_open_unique', ['house_id'], 'end_date IS NULL');

        Schema::create('water_meters', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('house_id')->constrained('houses');
            $table->string('meter_number', 50)->nullable();
            $table->date('installed_at');
            $table->date('removed_at')->nullable();
            $table->quantity('initial_reading')->default(0);
            $table->quantity('final_reading')->nullable();
            // Sisa pemakaian meter lama yang belum ditagih, dibebankan ke
            // pemakaian pertama meter ini (penggantian meter).
            $table->quantity('carried_volume')->default(0);
            $table->string('status', 20)->default('active');
            $table->text('notes')->nullable();
            $table->auditFields();
            $table->timestampsTz();

            $table->index(['house_id', 'status']);
        });
        Constraints::check('water_meters', 'water_meters_status_check', "status IN ('active', 'replaced', 'broken', 'inactive')");
        Constraints::partialUnique('water_meters', 'water_meters_house_active_unique', ['house_id'], "status = 'active'");

        Schema::create('water_tariffs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->community();
            $table->string('code', 50);
            $table->string('name', 100);
            $table->string('calculation_method', 30)->default('flat');
            $table->string('unit_name', 30)->default('m³');
            $table->string('status', 20)->default('active');
            $table->auditFields();
            $table->timestampsTz();

            $table->unique(['community_id', 'code']);
        });

        Schema::create('water_tariff_periods', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('water_tariff_id')->constrained('water_tariffs');
            $table->date('effective_from');
            $table->date('effective_until')->nullable();
            $table->money('base_fee')->default(0);       // abodemen
            $table->money('rate_per_unit');              // per m³
            $table->money('minimum_charge')->nullable(); // total minimum, opsional
            $table->text('notes')->nullable();
            $table->auditFields();
            $table->timestampsTz();

            $table->index(['water_tariff_id', 'effective_from']);
        });
        Constraints::check('water_tariff_periods', 'water_tariff_periods_dates_check', 'effective_until IS NULL OR effective_until >= effective_from');
        Constraints::check('water_tariff_periods', 'water_tariff_periods_amounts_check', 'base_fee >= 0 AND rate_per_unit >= 0 AND (minimum_charge IS NULL OR minimum_charge >= 0)');

        Schema::create('water_meter_readings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('water_meter_id')->constrained('water_meters');
            $table->foreignUuid('house_id')->constrained('houses');
            // Bulan pemakaian, selalu tanggal 1 (mis. 2026-09-01 = September).
            $table->date('reading_period');
            $table->quantity('reading_value');
            $table->timestampTz('reading_date');
            $table->foreignUuid('photo_file_id')->nullable()->constrained('files');
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('pending');
            $table->jsonb('anomalies')->nullable();
            $table->foreignId('validated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('validated_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->text('notes')->nullable();
            $table->timestampsTz();

            $table->index(['house_id', 'reading_period']);
            $table->index(['reading_period', 'status']);
        });
        Constraints::check('water_meter_readings', 'water_meter_readings_status_check', "status IN ('pending', 'validated', 'rejected')");
        Constraints::check('water_meter_readings', 'water_meter_readings_value_check', 'reading_value >= 0');
        // Satu pembacaan sah per meter per bulan; yang ditolak boleh dicatat ulang.
        Constraints::partialUnique('water_meter_readings', 'water_meter_readings_period_unique', ['water_meter_id', 'reading_period'], "status <> 'rejected'");

        Schema::create('water_reading_corrections', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('water_meter_reading_id')->constrained('water_meter_readings');
            $table->quantity('old_value');
            $table->quantity('new_value');
            $table->text('reason');
            $table->foreignUuid('photo_file_id')->nullable()->constrained('files');
            $table->string('status', 20)->default('pending');
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('decided_at')->nullable();
            $table->text('decision_notes')->nullable();
            $table->timestampsTz();

            $table->index(['status', 'created_at']);
        });
        Constraints::check('water_reading_corrections', 'water_reading_corrections_status_check', "status IN ('pending', 'approved', 'rejected')");

        Schema::create('water_usage_records', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('water_customer_id')->constrained('water_customers');
            $table->foreignUuid('house_id')->constrained('houses');
            $table->foreignUuid('water_meter_id')->constrained('water_meters');
            $table->date('usage_period');
            $table->foreignUuid('previous_reading_id')->nullable()->constrained('water_meter_readings');
            $table->foreignUuid('current_reading_id')->unique()->constrained('water_meter_readings');
            $table->quantity('previous_value');
            $table->quantity('current_value');
            $table->quantity('usage_volume');
            $table->foreignUuid('tariff_period_id')->constrained('water_tariff_periods');
            $table->money('base_fee');
            $table->money('rate_per_unit');
            $table->money('usage_amount');
            $table->money('calculated_amount');
            $table->jsonb('calculation_snapshot');
            // Diisi Fase 5 saat masuk invoice; setelah itu tidak boleh berubah.
            $table->timestampTz('billed_at')->nullable();
            $table->timestampsTz();

            $table->index(['house_id', 'usage_period']);
            $table->index(['usage_period', 'billed_at']);
        });
        Constraints::check('water_usage_records', 'water_usage_records_amounts_check', 'usage_volume >= 0 AND calculated_amount >= 0');
    }

    public function down(): void
    {
        Schema::dropIfExists('water_usage_records');
        Schema::dropIfExists('water_reading_corrections');
        Schema::dropIfExists('water_meter_readings');
        Schema::dropIfExists('water_tariff_periods');
        Schema::dropIfExists('water_tariffs');
        Schema::dropIfExists('water_meters');
        Schema::dropIfExists('water_customers');
    }
};
