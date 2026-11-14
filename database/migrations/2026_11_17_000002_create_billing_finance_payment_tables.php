<?php

use App\Support\Database\Constraints;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 5 — Billing (FR-BIL-*, Q8, S-4), Fase 6 — Kas (FR-FIN-*, Q12, D-07),
 * Fase 7 — Pembayaran (FR-PAY-*, Q10, S-5).
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── Billing ──────────────────────────────────────────────────────
        Schema::create('billing_periods', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->community();
            $table->string('period_code', 7); // YYYY-MM
            $table->date('period_start');
            $table->date('period_end');
            $table->string('status', 20)->default('open');
            $table->auditFields();
            $table->timestampsTz();

            $table->unique(['community_id', 'period_code']);
        });
        Constraints::check('billing_periods', 'billing_periods_status_check', "status IN ('open', 'closed')");

        // S-4: "BM — Belum Menempati": rumah dibebaskan sampai tanggal tertentu.
        Schema::create('billing_exemptions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->community();
            $table->foreignUuid('house_id')->constrained('houses');
            $table->string('billing_type', 30)->nullable(); // null = semua jenis
            $table->date('valid_from');
            $table->date('valid_until');
            $table->string('reason', 255);
            $table->auditFields();
            $table->timestampsTz();

            $table->index(['house_id', 'valid_from', 'valid_until']);
        });
        Constraints::check('billing_exemptions', 'billing_exemptions_dates_check', 'valid_until >= valid_from');

        Schema::create('invoices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->community();
            $table->string('invoice_number', 50);
            $table->foreignUuid('house_id')->constrained('houses');
            $table->foreignUuid('billing_period_id')->constrained('billing_periods');
            $table->string('invoice_type', 30);
            $table->string('billing_key', 80);
            $table->foreignUuid('contribution_type_id')->nullable()->constrained('contribution_types');
            $table->date('invoice_date');
            $table->date('due_date')->nullable();
            $table->money('total_amount');
            $table->money('paid_amount')->default(0);
            $table->money('outstanding_amount');
            $table->string('status', 30)->default('draft');
            $table->timestampTz('issued_at')->nullable();
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('paid_at')->nullable();
            $table->timestampTz('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('cancellation_reason')->nullable();
            $table->auditFields();
            $table->timestampsTz();

            $table->unique(['community_id', 'invoice_number']);
            $table->index(['house_id', 'billing_period_id']);
            $table->index(['status', 'due_date']);
        });
        Constraints::check('invoices', 'invoices_status_check', "status IN ('draft', 'issued', 'partially_paid', 'paid', 'overdue', 'cancelled')");
        Constraints::check('invoices', 'invoices_type_check', "invoice_type IN ('water', 'waste', 'contribution', 'other')");
        Constraints::check('invoices', 'invoices_amounts_check', 'total_amount >= 0 AND paid_amount >= 0 AND outstanding_amount >= 0');
        Constraints::partialUnique('invoices', 'invoices_house_period_key_unique', ['house_id', 'billing_period_id', 'billing_key'], "status <> 'cancelled'");

        Schema::create('invoice_details', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->string('component_type', 30);
            $table->string('source_type', 60)->nullable();
            $table->uuid('source_id')->nullable();
            $table->string('description', 255);
            $table->quantity('quantity')->default(1);
            $table->money('unit_price');
            $table->money('subtotal');
            $table->jsonb('calculation_snapshot')->nullable();
            $table->timestampsTz();

            $table->index('invoice_id');
            $table->index(['source_type', 'source_id']);
        });

        // ── Kas ──────────────────────────────────────────────────────────
        Schema::create('cash_accounts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->community();
            $table->string('account_code', 50);
            $table->string('account_name', 100);
            $table->string('account_type', 30)->default('cash');
            $table->money('opening_balance')->default(0);
            $table->date('opening_date');
            $table->boolean('is_default')->default(false);
            $table->string('status', 20)->default('active');
            $table->auditFields();
            $table->timestampsTz();

            $table->unique(['community_id', 'account_code']);
        });
        Constraints::check('cash_accounts', 'cash_accounts_type_check', "account_type IN ('cash', 'bank')");

        Schema::create('finance_categories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->community();
            $table->string('code', 50);
            $table->string('name', 100);
            $table->string('transaction_type', 10);
            $table->string('status', 20)->default('active');
            $table->auditFields();
            $table->timestampsTz();

            $table->unique(['community_id', 'code']);
        });
        Constraints::check('finance_categories', 'finance_categories_type_check', "transaction_type IN ('in', 'out')");

        // ── Pembayaran ───────────────────────────────────────────────────
        Schema::create('payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->community();
            $table->string('payment_number', 50);
            $table->foreignUuid('house_id')->constrained('houses');
            $table->foreignId('payer_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('payment_date');
            $table->money('amount');
            $table->string('payment_method', 30);
            $table->string('external_reference', 100)->nullable();
            $table->foreignUuid('proof_file_id')->nullable()->constrained('files');
            $table->foreignUuid('cash_account_id')->nullable()->constrained('cash_accounts');
            $table->string('status', 30)->default('pending_verification');
            $table->timestampTz('submitted_at')->nullable();
            $table->timestampTz('verified_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('rejection_reason')->nullable();
            $table->text('notes')->nullable();
            $table->auditFields();
            $table->timestampsTz();

            $table->unique(['community_id', 'payment_number']);
            $table->index(['status', 'payment_date']);
            $table->index('house_id');
        });
        Constraints::check('payments', 'payments_amount_check', 'amount > 0');
        Constraints::check('payments', 'payments_status_check', "status IN ('pending_verification', 'correction_required', 'verified', 'rejected', 'cancelled', 'reversed')");
        Constraints::check('payments', 'payments_method_check', "payment_method IN ('transfer', 'cash')");

        Schema::create('payment_allocations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('payment_id')->constrained('payments');
            $table->foreignUuid('invoice_id')->constrained('invoices');
            $table->money('allocated_amount');
            $table->timestampsTz();

            $table->unique(['payment_id', 'invoice_id']);
            $table->index('invoice_id');
        });
        Constraints::check('payment_allocations', 'payment_allocations_amount_check', 'allocated_amount > 0');

        Schema::create('payment_verifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('payment_id')->constrained('payments');
            $table->string('verification_status', 30);
            $table->text('notes')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('verified_at');
            $table->timestampsTz();
        });

        Schema::create('cash_transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->community();
            $table->foreignUuid('cash_account_id')->constrained('cash_accounts');
            $table->foreignUuid('finance_category_id')->constrained('finance_categories');
            $table->string('transaction_number', 50);
            $table->date('transaction_date');
            $table->string('transaction_type', 10);
            $table->money('amount');
            $table->text('description');
            $table->string('counterparty', 150)->nullable();
            // D-07: satu pembayaran → maksimal satu penerimaan kas.
            $table->foreignUuid('payment_id')->nullable()->unique()->constrained('payments');
            $table->uuid('reversal_of_id')->nullable()->unique();
            $table->foreignUuid('evidence_file_id')->nullable()->constrained('files');
            $table->string('status', 20);
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->auditFields();
            $table->timestampsTz();

            $table->unique(['community_id', 'transaction_number']);
            $table->index(['cash_account_id', 'transaction_date']);
            $table->index(['community_id', 'transaction_type', 'status']);
        });
        // FK ke tabel sendiri dipasang setelah primary key ada (PostgreSQL).
        Schema::table('cash_transactions', function (Blueprint $table) {
            $table->foreign('reversal_of_id')->references('id')->on('cash_transactions');
        });
        Constraints::check('cash_transactions', 'cash_transactions_amount_check', 'amount > 0');
        Constraints::check('cash_transactions', 'cash_transactions_type_check', "transaction_type IN ('in', 'out')");
        Constraints::check('cash_transactions', 'cash_transactions_status_check', "status IN ('pending_approval', 'posted', 'rejected')");
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_transactions');
        Schema::dropIfExists('payment_verifications');
        Schema::dropIfExists('payment_allocations');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('finance_categories');
        Schema::dropIfExists('cash_accounts');
        Schema::dropIfExists('invoice_details');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('billing_exemptions');
        Schema::dropIfExists('billing_periods');
    }
};
