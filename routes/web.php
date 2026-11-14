<?php

use App\Http\Controllers\Account\NotificationController;
use App\Http\Controllers\Account\PasswordController;
use App\Http\Controllers\Api\DashboardNotificationController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Billing\InvoiceController;
use App\Http\Controllers\Billing\MyBillController;
use App\Http\Controllers\Communication\AnnouncementController;
use App\Http\Controllers\Communication\ComplaintController;
use App\Http\Controllers\Contribution\ContributionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FileController;
use App\Http\Controllers\Finance\CashController;
use App\Http\Controllers\Payment\PaymentController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\Public\ContactController;
use App\Http\Controllers\Public\PageController;
use App\Http\Controllers\Reporting\ReportController;
use App\Http\Controllers\Resident\BlockController;
use App\Http\Controllers\Resident\HouseController;
use App\Http\Controllers\Resident\HouseOccupancyController;
use App\Http\Controllers\Resident\MyHouseController;
use App\Http\Controllers\Resident\OfficerAssignmentController;
use App\Http\Controllers\Resident\ResidentAccountController;
use App\Http\Controllers\Resident\ResidentController;
use App\Http\Controllers\Resident\ResidentImportController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\Settings\EmailLogController;
use App\Http\Controllers\Settings\SettingController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\Waste\WasteController;
use App\Http\Controllers\Water\MeterReadingController;
use App\Http\Controllers\Water\MeterRecordingController;
use App\Http\Controllers\Water\WaterCustomerController;
use App\Http\Controllers\Water\WaterTariffController;
use App\Http\Middleware\SetLocale;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Session-based web routes for the public landing page and the AdminLTE
| back office. Authentication uses the Laravel session guard (web).
|
*/

// ─── Language Switcher (session-based, no URL prefix) ─────────────────────────
Route::get('/lang/{locale}', function (string $locale) {
    if (in_array($locale, SetLocale::SUPPORTED_LOCALES, true)) {
        session(['locale' => $locale]);
    }

    return redirect()->back();
})->name('lang.switch');

// ─── Public Landing Page ──────────────────────────────────────────────────────
Route::get('/', [PageController::class, 'home'])->name('public.home');
Route::post('/contact', [ContactController::class, 'store'])
    ->middleware('throttle:5,1')
    ->name('public.contact.store');

// ─── Guest Routes ─────────────────────────────────────────────────────────────
Route::middleware('guest')->group(function () {
    Route::get(config('app.login_path'), [LoginController::class, 'showLoginForm'])->name('login');
    Route::post(config('app.login_path'), [LoginController::class, 'login'])
        ->middleware('throttle:login')
        ->name('login.submit');

    // Lupa password — di bawah ADMIN_LOGIN_PATH supaya URL publik tidak
    // membocorkan alamat login.
    Route::prefix(config('app.login_path'))->group(function () {
        Route::get('/forgot-password', [PasswordResetController::class, 'request'])->name('password.request');
        Route::post('/forgot-password', [PasswordResetController::class, 'email'])
            ->middleware('throttle:5,1')
            ->name('password.email');
        Route::get('/reset-password/{token}', [PasswordResetController::class, 'edit'])->name('password.reset');
        Route::post('/reset-password', [PasswordResetController::class, 'update'])
            ->middleware('throttle:5,1')
            ->name('password.update');
    });
});

// ─── Authenticated Routes ─────────────────────────────────────────────────────
Route::middleware('auth')->group(function () {

    // Logout
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

    // Ganti password sendiri — semua user login (FR-AUTH-003).
    Route::get('/account/password', [PasswordController::class, 'edit'])->name('account.password.edit');
    Route::put('/account/password', [PasswordController::class, 'update'])
        ->middleware('throttle:10,1')
        ->name('account.password.update');

    // File privat (foto meter, bukti transfer, lampiran) — otorisasi per file
    // lewat StoredFilePolicy + FileAccessRegistry, bukan gate menu.
    Route::get('/files/{file}', [FileController::class, 'show'])->name('files.show');

    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])
        ->name('dashboard')
        ->middleware('can:access-dashboard');

    // ─── Rumah & Penghuni (Fase 2, FR-RES-*) ─────────────────────────────────
    // Gate menu access-houses; izin baca/ubah yang lebih rinci (houses.view,
    // houses.manage, residents.*) diperiksa di controller/FormRequest.
    Route::middleware('can:access-houses')->group(function () {
        Route::resource('blocks', BlockController::class)->except(['show']);

        Route::middleware('can:residents.import')->group(function () {
            Route::get('/houses/import', [ResidentImportController::class, 'create'])->name('houses.import');
            Route::post('/houses/import', [ResidentImportController::class, 'store'])->name('houses.import.store');
            Route::get('/houses/import/template', [ResidentImportController::class, 'template'])->name('houses.import.template');
        });

        Route::resource('houses', HouseController::class);
        Route::post('/houses/{house}/occupancies', [HouseOccupancyController::class, 'store'])->name('houses.occupancies.store');
        Route::post('/occupancies/{houseResident}/end', [HouseOccupancyController::class, 'end'])->name('occupancies.end');

        Route::resource('residents', ResidentController::class)->except(['destroy']);
        Route::post('/residents/{resident}/account', [ResidentAccountController::class, 'store'])->name('residents.account.store');
        Route::post('/residents/{resident}/account/link', [ResidentAccountController::class, 'link'])->name('residents.account.link');
        Route::delete('/residents/{resident}/account/{user}', [ResidentAccountController::class, 'destroy'])->name('residents.account.destroy');
    });

    Route::middleware('can:access-officer-assignments')->group(function () {
        Route::get('/officer-assignments', [OfficerAssignmentController::class, 'index'])->name('officer-assignments.index');
        Route::post('/officer-assignments', [OfficerAssignmentController::class, 'store'])->name('officer-assignments.store');
        Route::post('/officer-assignments/{officerAssignment}/end', [OfficerAssignmentController::class, 'end'])->name('officer-assignments.end');
    });

    // ─── Air (Fase 3, FR-WAT-*) ──────────────────────────────────────────────
    // Petugas lapangan: catat meter dari HP (rumah sesuai penugasan).
    Route::middleware(['can:access-meter-recording', 'can:meter-readings.record'])
        ->prefix('meter-recording')->name('meter-recording.')->group(function () {
            Route::get('/', [MeterRecordingController::class, 'index'])->name('index');
            Route::get('/{house}', [MeterRecordingController::class, 'create'])->name('create');
            Route::post('/{house}', [MeterRecordingController::class, 'store'])->name('store');
        });

    // Ajukan koreksi angka — petugas maupun pengurus.
    Route::post('/meter-readings/{reading}/corrections', [MeterReadingController::class, 'requestCorrection'])
        ->middleware('can:meter-readings.correct')
        ->name('meter-readings.corrections.store');

    Route::middleware('can:access-water')->group(function () {
        Route::get('/meter-readings', [MeterReadingController::class, 'index'])->name('meter-readings.index');
        Route::get('/meter-readings/{reading}', [MeterReadingController::class, 'show'])->name('meter-readings.show');
        Route::post('/meter-readings/{reading}/validate', [MeterReadingController::class, 'validateReading'])->name('meter-readings.validate');
        Route::post('/meter-readings/{reading}/reject', [MeterReadingController::class, 'reject'])->name('meter-readings.reject');
        Route::post('/reading-corrections/{correction}/approve', [MeterReadingController::class, 'approveCorrection'])->name('reading-corrections.approve');
        Route::post('/reading-corrections/{correction}/reject', [MeterReadingController::class, 'rejectCorrection'])->name('reading-corrections.reject');

        Route::get('/water-customers', [WaterCustomerController::class, 'index'])->name('water-customers.index');
        Route::post('/houses/{house}/water-customer', [WaterCustomerController::class, 'subscribe'])->name('water-customers.subscribe');
        Route::post('/houses/{house}/water-meter/replace', [WaterCustomerController::class, 'replaceMeter'])->name('water-meters.replace');
        Route::post('/water-customers/{customer}/unsubscribe', [WaterCustomerController::class, 'unsubscribe'])->name('water-customers.unsubscribe');
        Route::post('/water-customers/{customer}/suspension', [WaterCustomerController::class, 'suspension'])->name('water-customers.suspension');

        Route::get('/water-tariffs', [WaterTariffController::class, 'index'])->name('water-tariffs.index');
        Route::post('/water-tariffs/periods', [WaterTariffController::class, 'storePeriod'])->name('water-tariffs.periods.store');
    });

    // ─── Sampah & Iuran (Fase 4) ─────────────────────────────────────────────
    Route::middleware('can:access-waste')->group(function () {
        Route::get('/waste', [WasteController::class, 'index'])->name('waste.index');
        Route::post('/waste/subscriptions', [WasteController::class, 'bulkSubscribe'])->name('waste.bulk-subscribe');
        Route::post('/houses/{house}/waste-subscription', [WasteController::class, 'subscribe'])->name('waste.subscribe');
        Route::post('/waste/subscriptions/{subscription}/end', [WasteController::class, 'unsubscribe'])->name('waste.unsubscribe');
        Route::post('/waste/fees', [WasteController::class, 'storeFee'])->name('waste.fees.store');
    });
    Route::middleware('can:access-waste-recording')->group(function () {
        Route::get('/waste/collection', [WasteController::class, 'collection'])->name('waste.collection');
        Route::post('/waste/collection', [WasteController::class, 'storeCollection'])->name('waste.collection.store');
    });
    Route::middleware('can:access-contributions')->group(function () {
        Route::get('/contributions', [ContributionController::class, 'index'])->name('contributions.index');
        Route::post('/contributions', [ContributionController::class, 'store'])->name('contributions.store');
        Route::post('/contributions/{type}/periods', [ContributionController::class, 'storePeriod'])->name('contributions.periods.store');
        Route::post('/contributions/{type}/toggle', [ContributionController::class, 'toggle'])->name('contributions.toggle');
        Route::post('/contributions/{type}/assign', [ContributionController::class, 'assign'])->name('contributions.assign');
    });

    // ─── Tagihan (Fase 5) ────────────────────────────────────────────────────
    Route::middleware('can:access-billing')->group(function () {
        Route::get('/invoices', [InvoiceController::class, 'index'])->name('invoices.index');
        Route::post('/invoices/generate', [InvoiceController::class, 'generate'])->name('invoices.generate');
        Route::post('/invoices/issue', [InvoiceController::class, 'issue'])->name('invoices.issue');
        Route::post('/invoices/{invoice}/cancel', [InvoiceController::class, 'cancel'])->name('invoices.cancel');
        Route::get('/billing-exemptions', [InvoiceController::class, 'exemptions'])->name('billing-exemptions.index');
        Route::post('/billing-exemptions', [InvoiceController::class, 'storeExemption'])->name('billing-exemptions.store');
        Route::post('/billing-exemptions/{exemption}/end', [InvoiceController::class, 'endExemption'])->name('billing-exemptions.end');
    });
    // Detail/cetak invoice: pengurus atau warga rumah itu (dicek di controller).
    Route::get('/invoices/{invoice}', [InvoiceController::class, 'show'])->name('invoices.show');

    // ─── Pembayaran (Fase 7) ─────────────────────────────────────────────────
    Route::middleware('can:access-payments')->group(function () {
        Route::get('/payments', [PaymentController::class, 'index'])->name('payments.index');
        Route::get('/payments/cash', [PaymentController::class, 'cash'])->name('payments.cash');
        Route::post('/payments/cash', [PaymentController::class, 'storeCash'])->name('payments.cash.store');
        Route::get('/payments/{payment}', [PaymentController::class, 'show'])->name('payments.show');
        Route::post('/payments/{payment}/verify', [PaymentController::class, 'verify'])->name('payments.verify');
        Route::post('/payments/{payment}/reject', [PaymentController::class, 'reject'])->name('payments.reject');
        Route::post('/payments/{payment}/reverse', [PaymentController::class, 'reverse'])->name('payments.reverse');
    });
    Route::middleware('can:access-my-bills')->group(function () {
        Route::get('/my-bills', [MyBillController::class, 'index'])->name('my-bills.index');
        Route::post('/my-bills/{house}/pay', [MyBillController::class, 'store'])->name('my-bills.pay');
        Route::post('/my-bills/payments/{payment}/resubmit', [MyBillController::class, 'resubmit'])->name('my-bills.resubmit');
        Route::post('/my-bills/payments/{payment}/cancel', [MyBillController::class, 'cancel'])->name('my-bills.cancel');
    });

    // ─── Kas (Fase 6) ────────────────────────────────────────────────────────
    Route::middleware('can:access-cash')->group(function () {
        Route::get('/cash', [CashController::class, 'index'])->name('cash.index');
        Route::post('/cash', [CashController::class, 'store'])->name('cash.store');
        Route::post('/cash/accounts', [CashController::class, 'storeAccount'])->name('cash.accounts.store');
        Route::post('/cash/{transaction}/approve', [CashController::class, 'approve'])->name('cash.approve');
        Route::post('/cash/{transaction}/reject', [CashController::class, 'reject'])->name('cash.reject');
        Route::post('/cash/{transaction}/reverse', [CashController::class, 'reverse'])->name('cash.reverse');
    });

    // ─── Komunikasi (Fase 8) ─────────────────────────────────────────────────
    Route::middleware('can:access-announcements')->group(function () {
        Route::get('/announcements', [AnnouncementController::class, 'index'])->name('announcements.index');
        Route::get('/announcements/create', [AnnouncementController::class, 'create'])->name('announcements.create');
        Route::post('/announcements', [AnnouncementController::class, 'store'])->name('announcements.store');
        Route::get('/announcements/{announcement}', [AnnouncementController::class, 'show'])->name('announcements.show');
        Route::get('/announcements/{announcement}/edit', [AnnouncementController::class, 'edit'])->name('announcements.edit');
        Route::put('/announcements/{announcement}', [AnnouncementController::class, 'update'])->name('announcements.update');
    });
    Route::middleware('can:access-complaints')->group(function () {
        Route::get('/complaints', [ComplaintController::class, 'index'])->name('complaints.index');
        Route::post('/complaints', [ComplaintController::class, 'store'])->name('complaints.store');
        Route::get('/complaints/{complaint}', [ComplaintController::class, 'show'])->name('complaints.show');
        Route::put('/complaints/{complaint}', [ComplaintController::class, 'update'])->name('complaints.update');
    });
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('/notifications/{id}', [NotificationController::class, 'open'])->name('notifications.open');
    Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');

    // ─── Laporan (Fase 9) ────────────────────────────────────────────────────
    Route::middleware(['can:access-reports', 'can:reports.view'])->prefix('reports')->name('reports.')->group(function () {
        Route::get('/', [ReportController::class, 'index'])->name('index');
        Route::get('/billing', [ReportController::class, 'billing'])->name('billing');
        Route::get('/arrears', [ReportController::class, 'arrears'])->name('arrears');
        Route::get('/cash-book', [ReportController::class, 'cashBook'])->name('cash-book');
        Route::get('/recap', [ReportController::class, 'recap'])->name('recap');
    });

    // Portal warga
    Route::get('/my-house', [MyHouseController::class, 'index'])
        ->middleware('can:access-my-house')
        ->name('my-house.index');

    // ─── User Management ─────────────────────────────────────────────────────
    Route::middleware('can:users.view')->group(function () {
        Route::resource('users', UserController::class)->except(['show']);
    });

    // ─── Role Management ─────────────────────────────────────────────────────
    Route::middleware('can:roles.view')->group(function () {
        Route::resource('roles', RoleController::class)->except(['show']);
    });

    // ─── Permission Management ───────────────────────────────────────────────
    Route::middleware('can:permissions.view')->group(function () {
        Route::resource('permissions', PermissionController::class)->except(['show']);
    });

    // ─── Audit Logs ───────────────────────────────────────────────────────────
    Route::middleware('can:access-audit-logs')->group(function () {
        Route::get('/audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');
    });

    // ─── Settings & Email Log ─────────────────────────────────────────────────
    Route::middleware('can:access-settings')->group(function () {
        Route::get('/settings', [SettingController::class, 'index'])->name('settings.index');
        Route::put('/settings/general', [SettingController::class, 'updateGeneral'])->name('settings.general.update');
        Route::put('/settings/mail', [SettingController::class, 'updateMail'])->name('settings.mail.update');
        Route::post('/settings/mail/test', [SettingController::class, 'sendTestEmail'])
            ->middleware('throttle:5,1')
            ->name('settings.mail.test');
        Route::get('/email-logs', [EmailLogController::class, 'index'])->name('email-logs.index');
    });

    // ─── Dashboard Notifications ─────────────────────────────────────────────
    // Notifikasi bersifat global (tidak per user), jadi dipagari izin sendiri —
    // sama dengan yang memagari lonceng di navbar.
    Route::middleware('can:access-notifications')->prefix('admin/notifications')->name('admin.notifications.')->group(function () {
        Route::get('/', [DashboardNotificationController::class, 'index'])->name('index');
        Route::get('/unread-count', [DashboardNotificationController::class, 'unreadCount'])->name('unread-count');
        Route::post('/{id}/read', [DashboardNotificationController::class, 'markAsRead'])->name('read');
        Route::post('/read-all', [DashboardNotificationController::class, 'markAllAsRead'])->name('read-all');
    });
});
