<?php

namespace Tests\Feature\Finance;

use App\Enums\Billing\InvoiceStatus;
use App\Enums\CommunityRole;
use App\Enums\Finance\CashTransactionStatus;
use App\Enums\Payment\PaymentStatus;
use App\Models\BillingExemption;
use App\Models\CashTransaction;
use App\Models\ContributionType;
use App\Models\FinanceCategory;
use App\Models\House;
use App\Models\Invoice;
use App\Models\User;
use App\Models\WaterUsageRecord;
use App\Services\Billing\BillingService;
use App\Services\Contribution\ContributionService;
use App\Services\Finance\CashService;
use App\Services\Payment\PaymentService;
use App\Services\Reporting\ReportService;
use App\Services\Resident\HouseService;
use App\Services\Resident\ResidencyService;
use App\Services\Waste\WasteService;
use App\Services\Water\MeterReadingService;
use App\Services\Water\WaterCustomerService;
use App\Services\Water\WaterTariffService;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

/**
 * Fase 4–7 end-to-end dengan angka sampel De Java Valley: air (5.000/m³ +
 * abodemen 5.000), sampah 30.000, perelek 5.000.
 */
class BillingPaymentCashTest extends TestCase
{
    use RefreshDatabase;

    private House $house;

    private House $bmHouse;

    private User $warga;

    private User $bendahara;

    private User $ketua;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('private');
        Carbon::setTestNow('2026-10-05 09:00:00');
        $this->seed();

        $this->bendahara = User::where('email', CommunityRole::Bendahara->demoEmail())->first();
        $this->ketua = User::where('email', CommunityRole::KetuaRt->demoEmail())->first();
        $this->warga = User::where('email', CommunityRole::Warga->demoEmail())->first();
        $this->actingAs($this->bendahara);

        $houses = app(HouseService::class);
        $block = $houses->createBlock(['code' => 'J']);
        $this->house = $houses->createHouse(['block_id' => $block->id, 'house_number' => '24']);
        $this->bmHouse = $houses->createHouse(['block_id' => $block->id, 'house_number' => '16']);

        $residency = app(ResidencyService::class);
        $resident = $residency->createResident(['full_name' => 'Zai']);
        $residency->linkAccount($resident, $this->warga);
        $residency->moveIn($this->house, $resident, ['start_date' => '2024-01-01']);

        app(WaterTariffService::class)->addPeriod(['effective_from' => '2026-01-01', 'base_fee' => 5000, 'rate_per_unit' => 5000]);
        app(WaterCustomerService::class)->subscribe($this->house, ['start_date' => '2026-01-01', 'initial_reading' => 280]);
        app(MeterReadingService::class)->record($this->house, Carbon::parse('2026-09-01'), 287, UploadedFile::fake()->image('m.jpg'), $this->bendahara);

        $waste = app(WasteService::class);
        $waste->addFeePeriod('2026-01-01', 30000);
        $waste->subscribe($this->house, '2026-01-01');
        $waste->subscribe($this->bmHouse, '2026-01-01');

        app(ContributionService::class)->createType([
            'name' => 'Iuran Perelek', 'contribution_category' => 'routine', 'applies_to' => 'all_houses', 'amount' => 5000, 'effective_from' => '2026-01-01',
        ]);

        BillingExemption::create(['house_id' => $this->bmHouse->id, 'valid_from' => '2026-01-01', 'valid_until' => '2026-12-31', 'reason' => 'BM s/d Des 26']);
        app(CashService::class)->ensureDefaults();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function generateAndIssue(): void
    {
        app(BillingService::class)->generate(Carbon::parse('2026-09-01'));
        app(BillingService::class)->issue(app(BillingService::class)->period(Carbon::parse('2026-09-01')), $this->bendahara);
    }

    public function test_generation_follows_sample_amounts_and_skips_exempt_house(): void
    {
        $result = app(BillingService::class)->generate(Carbon::parse('2026-09-01'));

        $this->assertSame(3, $result['created']);            // air + sampah + perelek J-24
        $this->assertSame(2, $result['skipped_exempt']);     // J-16 (BM): sampah + perelek

        $invoices = Invoice::where('house_id', $this->house->id)->get()->keyBy(fn ($i) => $i->invoice_type->value);
        $this->assertSame('40000.00', $invoices['water']->total_amount); // (287−280)×5.000 + 5.000
        $this->assertSame('30000.00', $invoices['waste']->total_amount);
        $this->assertSame('5000.00', $invoices['contribution']->total_amount);
        $this->assertSame('2026-10-01', $invoices['water']->invoice_date->toDateString());
        $this->assertSame('2026-09-30', $invoices['waste']->invoice_date->toDateString());
        $this->assertSame(2, $invoices['water']->details()->count());
        $this->assertNotNull(WaterUsageRecord::first()->billed_at);
        $this->assertSame(0, Invoice::where('house_id', $this->bmHouse->id)->count());

        // Idempoten.
        $again = app(BillingService::class)->generate(Carbon::parse('2026-09-01'));
        $this->assertSame(0, $again['created']);
        $this->assertSame(3, Invoice::count());
    }

    public function test_issue_notifies_resident_and_marks_overdue_later(): void
    {
        $this->generateAndIssue();

        $this->assertSame(3, Invoice::where('status', InvoiceStatus::Issued)->count());
        $this->assertSame(1, $this->warga->notifications()->count());

        $this->assertSame(3, app(BillingService::class)->markOverdue(Carbon::parse('2026-10-20')));
    }

    public function test_one_transfer_pays_several_invoices_after_verification(): void
    {
        $this->generateAndIssue();
        $invoices = Invoice::where('house_id', $this->house->id)->get();
        $payments = app(PaymentService::class);

        $payment = $payments->submit($this->house, $invoices->pluck('id')->all(), ['payment_date' => '2026-10-04', 'external_reference' => 'TRX-1'], UploadedFile::fake()->image('bukti.jpg'), $this->warga);

        $this->assertSame('75000.00', $payment->amount);
        $this->assertSame(PaymentStatus::PendingVerification, $payment->status);
        $this->assertSame(3, Invoice::where('status', InvoiceStatus::Issued)->count(), 'pengajuan tidak mengubah tagihan (BR-012)');
        $this->assertCount(0, $payments->payableInvoices($this->house), 'tagihan yang diajukan terkunci');

        $payments->verify($payment, $this->bendahara);

        $this->assertSame(3, Invoice::where('status', InvoiceStatus::Paid)->count());
        $this->assertSame(1, CashTransaction::where('payment_id', $payment->id)->count());
        $this->assertSame('75000.00', (string) app(CashService::class)->balance(app(CashService::class)->defaultAccount()));

        // EX-006: verifikasi ganda ditolak, kas tidak bertambah.
        try {
            $payments->verify($payment, $this->bendahara);
            $this->fail('Verifikasi ganda harus ditolak.');
        } catch (RuntimeException) {
            $this->assertSame(1, CashTransaction::count());
        }
    }

    public function test_duplicate_reference_and_foreign_invoice_are_refused(): void
    {
        $this->generateAndIssue();
        $invoices = Invoice::where('house_id', $this->house->id)->get();
        $payments = app(PaymentService::class);

        $payments->submit($this->house, [$invoices[0]->id], ['payment_date' => '2026-10-04', 'external_reference' => 'TRX-9'], UploadedFile::fake()->image('a.jpg'), $this->warga);

        $this->expectException(RuntimeException::class);
        $payments->submit($this->house, [$invoices[1]->id], ['payment_date' => '2026-10-04', 'external_reference' => 'TRX-9'], UploadedFile::fake()->image('b.jpg'), $this->warga);
    }

    public function test_cash_payment_is_verified_immediately_and_can_be_reversed(): void
    {
        $this->generateAndIssue();
        $payments = app(PaymentService::class);
        $ids = Invoice::where('house_id', $this->house->id)->pluck('id')->all();

        $payment = $payments->recordCash($this->house, $ids, ['payment_date' => '2026-10-05'], $this->bendahara);

        $this->assertSame(PaymentStatus::Verified, $payment->status);
        $this->assertSame('cash', $payment->payment_method);
        $account = app(CashService::class)->defaultAccount();
        $this->assertSame('75000.00', (string) app(CashService::class)->balance($account));

        $payments->reverse($payment, $this->bendahara, 'Salah rumah');

        $this->assertSame(PaymentStatus::Reversed, $payment->fresh()->status);
        $this->assertSame(3, Invoice::whereIn('status', [InvoiceStatus::Issued, InvoiceStatus::Overdue])->count());
        $this->assertSame('0.00', (string) app(CashService::class)->balance($account));
        $this->assertSame(2, CashTransaction::count(), 'penerimaan + pembalik, tidak ada yang dihapus');
    }

    public function test_rejected_payment_frees_invoices_and_posts_nothing(): void
    {
        $this->generateAndIssue();
        $payments = app(PaymentService::class);
        $ids = Invoice::where('house_id', $this->house->id)->pluck('id')->all();
        $payment = $payments->submit($this->house, $ids, ['payment_date' => '2026-10-04'], UploadedFile::fake()->image('x.jpg'), $this->warga);

        $payments->reject($payment, $this->bendahara, 'Nominal tidak sesuai');

        $this->assertCount(3, $payments->payableInvoices($this->house));
        $this->assertSame(0, CashTransaction::count());
    }

    public function test_expense_needs_ketua_approval_and_sufficient_balance(): void
    {
        $cash = app(CashService::class);
        $account = $cash->defaultAccount();
        $in = FinanceCategory::where('code', 'SUMBANGAN')->first();
        $out = FinanceCategory::where('code', 'KEGIATAN')->first();

        $cash->recordIncome(['cash_account_id' => $account->id, 'finance_category_id' => $in->id, 'transaction_date' => '2026-10-03', 'amount' => 1000000, 'description' => 'Sumbangan donatur'], $this->bendahara);
        $expense = $cash->recordExpense(['cash_account_id' => $account->id, 'finance_category_id' => $out->id, 'transaction_date' => '2026-10-04', 'amount' => 300000, 'description' => 'Santunan'], $this->bendahara);

        $this->assertSame(CashTransactionStatus::PendingApproval, $expense->status);
        $this->assertSame('1000000.00', (string) $cash->balance($account), 'pengeluaran pending belum mengurangi saldo');

        try {
            $cash->approve($expense, $this->bendahara);
            $this->fail('Pencatat tidak boleh menyetujui sendiri.');
        } catch (RuntimeException) {
            $this->addToAssertionCount(1);
        }

        $cash->approve($expense, $this->ketua);
        $this->assertSame('700000.00', (string) $cash->balance($account));

        $big = $cash->recordExpense(['cash_account_id' => $account->id, 'finance_category_id' => $out->id, 'transaction_date' => '2026-10-04', 'amount' => 900000, 'description' => 'Terlalu besar'], $this->bendahara);
        $this->expectException(RuntimeException::class);
        $cash->approve($big, $this->ketua);
    }

    public function test_cancelled_water_invoice_releases_usage_for_rebilling(): void
    {
        app(BillingService::class)->generate(Carbon::parse('2026-09-01'), ['water']);
        $invoice = Invoice::firstOrFail();

        app(BillingService::class)->cancel($invoice, $this->bendahara, 'Salah hitung');

        $this->assertNull(WaterUsageRecord::first()->billed_at);
        $this->assertSame(1, app(BillingService::class)->generate(Carbon::parse('2026-09-01'), ['water'])['created']);
    }

    public function test_tariff_change_does_not_touch_issued_invoices(): void
    {
        $this->generateAndIssue();
        app(WasteService::class)->addFeePeriod('2026-11-01', 35000);
        app(ContributionService::class)->addPeriod(ContributionType::first(), '2026-11-01', 10000);

        $this->assertSame('30000.00', Invoice::where('invoice_type', 'waste')->first()->total_amount);
        $this->assertSame('5000.00', Invoice::where('invoice_type', 'contribution')->first()->total_amount);
    }

    public function test_billing_report_matches_invoices(): void
    {
        $this->generateAndIssue();
        $data = app(ReportService::class)->billingList(Carbon::parse('2026-09-01'));

        $row = $data['rows']->firstWhere(fn ($r) => $r['house']->id === $this->house->id);
        $this->assertSame('75000.00', (string) $row['current']);
        $this->assertSame('35000.00', (string) $row['water_usage_amount']);
        $this->assertSame('5000.00', (string) $row['water_base_fee']);
        $this->assertFalse($data['rows']->firstWhere(fn ($r) => $r['house']->id === $this->bmHouse->id)['has_invoices']);
        $this->assertSame('75000.00', (string) Money::of($data['totals']['grand']));
    }
}
