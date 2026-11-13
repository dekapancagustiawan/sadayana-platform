<?php

namespace App\Providers;

use App\Enums\RoleType;
use App\Policies\PermissionPolicy;
use App\Policies\RolePolicy;
use App\Services\Settings\SettingsService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        Blueprint::macro('auditFields', function () {
            $this->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $this->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $this->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function boot(): void
    {
        // Nama aplikasi & konfigurasi email dari layar Settings menimpa .env.
        // Aman sebelum migrasi: Setting::allCached() mengembalikan [] bila
        // tabelnya belum ada.
        $this->app->make(SettingsService::class)->applyToConfig();

        $this->configureRateLimiting();

        // Register policies for Spatie models
        Gate::policy(Role::class, RolePolicy::class);
        Gate::policy(Permission::class, PermissionPolicy::class);

        // Implicitly grant "Admin" role all permissions
        Gate::before(function ($user, $ability) {
            return $user->hasRole(RoleType::Admin->value) ? true : null;
        });

        // Gate menu `access-*` (access-dashboard, access-user-management,
        // access-audit-logs, dan seterusnya) TIDAK lagi didefinisikan di sini.
        //
        // Dulu masing-masing berupa closure berisi daftar role literal, yang
        // membuat setiap role baru harus ditambahkan lewat perubahan kode.
        // Sekarang nama-nama itu ada sebagai permission di database
        // (MenuAccessPermissionsSeeder) dan di-resolve oleh `Gate::before`
        // milik Spatie — lihat PermissionRegistrar::registerPermissions(), yang
        // mencocokkan nama ability APA PUN ke izin milik user, termasuk nama
        // bertanda hubung. Jadi `Gate::allows('access-dashboard')`,
        // `@can('access-dashboard')`, dan middleware `can:access-dashboard` tetap bekerja
        // sama persis, hanya sumbernya berpindah ke database.
        //
        // Konsekuensinya: role baru cukup dibuat dan dicentang izinnya di layar
        // Role Management, tanpa menyentuh file ini. Permission yang belum ada
        // di database mengembalikan false (bukan exception), karena
        // `checkPermissionTo()` menangkap PermissionDoesNotExist.
        //
        // Matriks izin per role dikunci tests/Feature/RoleMenuAccessTest.php.
        // Admin tetap superuser lewat `Gate::before` di atas — pintu darurat
        // kalau izin di database salah dikonfigurasi.
    }

    /**
     * Limiter `login` dipakai login web dan login API (middleware throttle:login).
     *
     * Dua batas sekaligus: per email+IP (menahan tebak password satu akun) dan
     * per IP (menahan satu IP yang mencoba banyak email berbeda).
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('login', function (Request $request) {
            $email = Str::lower((string) $request->input('email'));

            $response = function (Request $request, array $headers) {
                $seconds = (int) ($headers['Retry-After'] ?? 60);
                $message = "Too many login attempts. Please try again in {$seconds} seconds.";

                if ($request->is('api/*') || $request->expectsJson()) {
                    return response()->json([
                        'status' => 'error',
                        'message' => $message,
                    ], 429, $headers);
                }

                return redirect()->back()
                    ->withInput($request->only('email', 'remember'))
                    ->withErrors(['email' => $message]);
            };

            return [
                Limit::perMinute(5)->by('email:'.$email.'|'.$request->ip())->response($response),
                Limit::perMinute(20)->by('ip:'.$request->ip())->response($response),
            ];
        });
    }
}
