<?php

use App\Http\Controllers\Api\DashboardNotificationController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\Public\ContactController;
use App\Http\Controllers\Public\PageController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\Settings\EmailLogController;
use App\Http\Controllers\Settings\SettingController;
use App\Http\Controllers\UserController;
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
});

// ─── Authenticated Routes ─────────────────────────────────────────────────────
Route::middleware('auth')->group(function () {

    // Logout
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])
        ->name('dashboard')
        ->middleware('can:access-dashboard');

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
