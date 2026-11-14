<?php

namespace App\Providers;

use App\Enums\RoleType;
use App\Models\Payment;
use App\Models\StoredFile;
use App\Policies\PermissionPolicy;
use App\Policies\RolePolicy;
use App\Policies\StoredFilePolicy;
use App\Services\Communication\CommunityService;
use App\Services\Finance\CashService;
use App\Services\Payment\PaymentService;
use App\Services\Resident\HouseAccessService;
use App\Services\Settings\SettingsService;
use App\Services\Water\MeterPhotoAccess;
use App\Services\Water\MeterReadingService;
use App\Support\Authorization;
use App\Support\CurrentCommunity;
use App\Support\FileAccessRegistry;
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

        // Tipe kolom standar Dok 06 §2.2 — jangan pakai float/double untuk uang.
        Blueprint::macro('money', function (string $column) {
            return $this->decimal($column, 18, 2);
        });

        Blueprint::macro('quantity', function (string $column) {
            return $this->decimal($column, 14, 3);
        });

        // FK ke komunitas untuk tabel tingkat atas (pasangan trait BelongsToCommunity).
        Blueprint::macro('community', function () {
            return $this->foreignUuid('community_id')->constrained('communities');
        });

        // Scoped: di-cache per request/job dan direset antar test.
        $this->app->scoped(CurrentCommunity::class);
        $this->app->singleton(FileAccessRegistry::class);
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
        Gate::policy(StoredFile::class, StoredFilePolicy::class);

        // Aturan akses file privat per kategori (lihat FileAccessRegistry).
        $files = $this->app->make(FileAccessRegistry::class);
        $files->register(MeterReadingService::PHOTO_CATEGORY, fn ($user, $file) => $this->app->make(MeterPhotoAccess::class)($user, $file));
        // Bukti transfer: bendahara/pengurus, atau warga rumah pembayarnya.
        $files->register(PaymentService::PROOF_CATEGORY, function ($user, $file) {
            if ($user->can('payments.view')) {
                return true;
            }
            $payment = Payment::where('proof_file_id', $file->getKey())->first();

            return $payment && $this->app->make(HouseAccessService::class)->housesFor($user)->contains('id', $payment->house_id);
        });
        $files->register(CashService::EVIDENCE_CATEGORY, fn ($user) => $user->can('cash.view'));
        $files->register(CommunityService::ATTACHMENT_CATEGORY, fn ($user) => $user->can('complaints.manage'));

        // Admin lolos semua gate, KECUALI ability keuangan yang dipisahkan
        // (Authorization::SEGREGATED_ABILITIES): untuk itu Admin harus punya
        // permission eksplisit, sama seperti role lain (SRS ROLE-001).
        Gate::before(function ($user, $ability) {
            if (Authorization::isSegregated($ability)) {
                return null;
            }

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
            // Web memakai field `login` (email/nomor HP), API masih boleh `email`.
            $email = Str::lower((string) ($request->input('login') ?? $request->input('email')));

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
                    ->withInput($request->only('login', 'remember'))
                    ->withErrors(['login' => $message]);
            };

            return [
                Limit::perMinute(5)->by('email:'.$email.'|'.$request->ip())->response($response),
                Limit::perMinute(20)->by('ip:'.$request->ip())->response($response),
            ];
        });
    }
}
