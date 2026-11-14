<?php

namespace Tests\Feature;

use App\Enums\Billing\InvoiceStatus;
use App\Enums\CommunityRole;
use App\Enums\Payment\PaymentStatus;
use App\Models\Announcement;
use App\Models\CashAccount;
use App\Models\CashTransaction;
use App\Models\Complaint;
use App\Models\House;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use App\Models\WaterMeterReading;
use App\Services\Finance\CashService;
use App\Services\Payment\PaymentService;
use App\Services\Reporting\ReportService;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Data demo terbentuk lengkap & setiap layar bisa dibuka oleh role yang
 * berhak (dan ditolak untuk yang tidak).
 */
class DemoDataSmokeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('private');
        $this->seed();
        $this->seed(DemoDataSeeder::class);
    }

    private function demo(CommunityRole $role): User
    {
        return User::where('email', $role->demoEmail())->firstOrFail();
    }

    public function test_demo_data_is_consistent(): void
    {
        $this->assertSame(29, House::count());
        $this->assertGreaterThan(200, Invoice::count());
        $this->assertSame(0, Invoice::where('status', InvoiceStatus::Draft)->count());
        $this->assertSame(2, Payment::where('status', PaymentStatus::PendingVerification)->count());
        $this->assertSame(1, Payment::where('status', PaymentStatus::CorrectionRequired)->count());
        $this->assertSame(1, WaterMeterReading::where('status', 'pending')->count());
        $this->assertSame(1, CashTransaction::where('status', 'pending_approval')->count());
        $this->assertSame(3, Announcement::count());
        $this->assertSame(2, Complaint::count());

        // Setiap pembayaran terverifikasi punya tepat satu penerimaan kas.
        $this->assertSame(Payment::where('status', PaymentStatus::Verified)->count(), CashTransaction::whereNotNull('payment_id')->count());

        foreach (CashAccount::all() as $account) {
            $this->assertFalse(app(CashService::class)->balance($account)->isNegative(), $account->account_name);
        }

        // Rumah BM tidak punya tagihan; rumah menunggak kronis punya > 3 bulan lewat jatuh tempo.
        $this->assertSame(0, Invoice::where('house_id', House::firstWhere('house_code', 'J-16')->id)->count());
        $arrears = app(ReportService::class)->arrears();
        $this->assertTrue($arrears->firstWhere(fn ($r) => $r['house']->house_code === 'J-21')['flag']);
    }

    public function test_every_page_renders_for_its_roles(): void
    {
        $house = House::firstWhere('house_code', 'J-24');
        $invoice = Invoice::where('house_id', $house->id)->first();
        $payment = Payment::where('status', PaymentStatus::PendingVerification)->first();
        $reading = WaterMeterReading::where('status', 'pending')->first();
        $complaint = Complaint::first();
        $announcement = Announcement::first();

        $pages = [
            CommunityRole::Bendahara->value => [
                route('dashboard'), route('invoices.index', ['month' => '2026-09']), route('invoices.show', $invoice), route('invoices.show', [$invoice, 'print' => 1]),
                route('billing-exemptions.index'), route('payments.index'), route('payments.index', ['status' => 'all']), route('payments.show', $payment),
                route('payments.cash', ['house' => $house->id]), route('cash.index'), route('cash.index', ['month' => '2026-08']),
                route('reports.index'), route('reports.billing', ['month' => '2026-09']), route('reports.billing', ['month' => '2026-09', 'print' => 1]),
                route('reports.arrears'), route('reports.cash-book', ['month' => '2026-08']), route('reports.cash-book', ['month' => '2026-08', 'print' => 1]),
                route('reports.recap', ['year' => 2026]), route('waste.index'), route('contributions.index'), route('water-tariffs.index'),
                route('meter-readings.index', ['period' => '2026-10']), route('water-customers.index'), route('houses.show', $house), route('complaints.index'),
                route('notifications.index'),
            ],
            CommunityRole::KetuaRt->value => [
                route('dashboard'), route('cash.index'), route('meter-readings.show', $reading), route('complaints.show', $complaint),
                route('announcements.create'), route('announcements.edit', $announcement), route('officer-assignments.index'), route('reports.arrears'),
            ],
            CommunityRole::PetugasLapangan->value => [route('dashboard'), route('meter-recording.index'), route('meter-recording.create', ['house' => House::firstWhere('house_code', 'C-1'), 'period' => '2026-10'])],
            CommunityRole::PetugasSampah->value => [route('dashboard'), route('waste.collection'), route('complaints.index')],
            CommunityRole::Warga->value => [
                route('dashboard'), route('my-house.index'), route('my-bills.index'), route('invoices.show', $invoice),
                route('announcements.index'), route('announcements.show', $announcement), route('complaints.index'), route('notifications.index'),
            ],
        ];

        foreach ($pages as $role => $urls) {
            $user = $this->demo(CommunityRole::from($role));
            foreach ($urls as $url) {
                $this->actingAs($user)->get($url)->assertOk();
            }
        }

        $csv = $this->actingAs($this->demo(CommunityRole::Bendahara))->get(route('reports.billing', ['month' => '2026-09', 'format' => 'csv']));
        $csv->assertOk();
        $this->assertStringContainsString('J-24', $csv->streamedContent());

        $this->actingAs($this->demo(CommunityRole::Warga))->get(route('my-bills.index'))->assertSee('Kirim bukti pembayaran');
    }

    public function test_roles_are_denied_outside_their_area(): void
    {
        $warga = $this->demo(CommunityRole::Warga);
        foreach (['invoices.index', 'payments.index', 'cash.index', 'reports.index', 'waste.index', 'meter-readings.index'] as $route) {
            $this->actingAs($warga)->get(route($route))->assertForbidden();
        }

        // Warga tidak bisa membuka tagihan rumah lain.
        $other = Invoice::where('house_id', House::firstWhere('house_code', 'J-25')->id)->first();
        $this->actingAs($warga)->get(route('invoices.show', $other))->assertForbidden();

        $ketua = $this->demo(CommunityRole::KetuaRt);
        $payment = Payment::where('status', PaymentStatus::PendingVerification)->first();
        $this->actingAs($ketua)->post(route('payments.verify', $payment))->assertForbidden();

        // Admin tidak otomatis boleh memverifikasi pembayaran (SRS ROLE-001).
        $admin = User::where('email', 'admin@example.com')->first();
        $this->actingAs($admin)->post(route('payments.verify', $payment))->assertForbidden();
        $this->actingAs($admin)->get(route('payments.show', $payment))->assertOk();
    }

    public function test_resident_pays_through_the_portal_and_bendahara_verifies(): void
    {
        $warga = $this->demo(CommunityRole::Warga);
        $house = House::firstWhere('house_code', 'J-24');
        $open = app(PaymentService::class)->payableInvoices($house);
        $this->assertNotEmpty($open);

        $this->actingAs($warga)->post(route('my-bills.pay', $house), [
            'invoice_ids' => $open->pluck('id')->all(),
            'payment_date' => today()->toDateString(),
            'proof' => UploadedFile::fake()->image('bukti.jpg'),
        ])->assertRedirect(route('my-bills.index'));

        $payment = Payment::where('house_id', $house->id)->latest('created_at')->first();
        $this->actingAs($warga)->get(route('files.show', $payment->proof_file_id))->assertOk();

        $this->actingAs($this->demo(CommunityRole::Bendahara))->post(route('payments.verify', $payment))->assertRedirect(route('payments.index'));
        $this->assertCount(0, app(PaymentService::class)->payableInvoices($house));
        $this->assertTrue($warga->notifications()->where('data->kind', 'payment')->exists());
    }
}
