<?php

namespace App\Services\Settings;

use App\Models\AuditLog;
use App\Models\Setting;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class SettingsService
{
    /** Key pengaturan umum yang disimpan apa adanya. */
    public const GENERAL_KEYS = [
        'app_name',
        'contact_email',
        'contact_phone',
        'contact_whatsapp',
        'contact_address',
        'social_instagram',
        'social_facebook',
        'social_tiktok',
        'social_youtube',
    ];

    /** Key pengaturan email selain password (yang disimpan terenkripsi). */
    public const MAIL_KEYS = [
        'mail_mailer',
        'mail_host',
        'mail_port',
        'mail_scheme',
        'mail_username',
        'mail_from_address',
        'mail_from_name',
    ];

    public const MAILERS = ['smtp', 'log'];

    /** `smtp` = plain/STARTTLS (25/587), `smtps` = SSL/TLS langsung (465). */
    public const MAIL_SCHEMES = ['smtp', 'smtps'];

    private const LOGO_DIR = 'settings';

    /**
     * Nilai untuk form, dengan default dari config supaya form tidak kosong
     * sebelum pertama kali disimpan. Password tidak pernah dikembalikan.
     *
     * @return array<string, mixed>
     */
    public function values(): array
    {
        return [
            'app_name' => Setting::get('app_name', config('app.name')),
            'app_logo_url' => $this->logoUrl(),
            'contact_email' => Setting::get('contact_email'),
            'contact_phone' => Setting::get('contact_phone'),
            'contact_whatsapp' => Setting::get('contact_whatsapp'),
            'contact_address' => Setting::get('contact_address'),
            'social_instagram' => Setting::get('social_instagram'),
            'social_facebook' => Setting::get('social_facebook'),
            'social_tiktok' => Setting::get('social_tiktok'),
            'social_youtube' => Setting::get('social_youtube'),

            'mail_mailer' => Setting::get('mail_mailer', config('mail.default')),
            'mail_host' => Setting::get('mail_host', config('mail.mailers.smtp.host')),
            'mail_port' => Setting::get('mail_port', config('mail.mailers.smtp.port')),
            'mail_scheme' => Setting::get('mail_scheme', config('mail.mailers.smtp.scheme') ?? 'smtp'),
            'mail_username' => Setting::get('mail_username', config('mail.mailers.smtp.username')),
            'mail_password_set' => Setting::get('mail_password') !== null,
            'mail_from_address' => Setting::get('mail_from_address', config('mail.from.address')),
            'mail_from_name' => Setting::get('mail_from_name', config('mail.from.name')),
        ];
    }

    /**
     * Identitas yang aman ditampilkan di halaman publik (landing, error page).
     * Sengaja terpisah dari values() supaya konfigurasi email tidak pernah
     * ikut terkirim ke view publik.
     *
     * @return array{name: string, logo_url: ?string, email: ?string, phone: ?string, whatsapp: ?string, whatsapp_url: ?string, address: ?string, socials: array<string, string>}
     */
    public function publicProfile(): array
    {
        $whatsapp = Setting::get('contact_whatsapp');

        $socials = [];
        foreach (['instagram', 'facebook', 'tiktok', 'youtube'] as $network) {
            if ($url = Setting::get("social_{$network}")) {
                $socials[$network] = $url;
            }
        }

        return [
            'name' => (string) Setting::get('app_name', config('app.name')),
            'logo_url' => $this->logoUrl(),
            'email' => Setting::get('contact_email'),
            'phone' => Setting::get('contact_phone'),
            'whatsapp' => $whatsapp,
            'whatsapp_url' => $whatsapp ? "https://wa.me/{$whatsapp}" : null,
            'address' => Setting::get('contact_address'),
            'socials' => $socials,
        ];
    }

    /**
     * Alamat penerima pesan form kontak: email kontak di Settings, atau alamat
     * pengirim default bila belum diisi.
     */
    public function contactRecipient(): ?string
    {
        return Setting::get('contact_email', config('mail.from.address'));
    }

    /**
     * Simpan pengaturan umum, termasuk logo (opsional) dan permintaan hapus logo.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateGeneral(array $data, ?UploadedFile $logo = null, bool $removeLogo = false): void
    {
        // File disimpan di luar transaksi (disk tidak bisa di-rollback); kalau
        // penyimpanan DB gagal, file baru dibuang lagi di catch.
        $newLogoPath = $logo ? $this->storeLogo($logo) : null;
        $oldLogoPath = Setting::get('app_logo');

        try {
            DB::transaction(function () use ($data, $newLogoPath, $removeLogo) {
                foreach (self::GENERAL_KEYS as $key) {
                    Setting::set($key, $data[$key] ?? null);
                }

                if ($newLogoPath) {
                    Setting::set('app_logo', $newLogoPath);
                } elseif ($removeLogo) {
                    Setting::set('app_logo', null);
                }
            });
        } catch (\Throwable $e) {
            if ($newLogoPath) {
                Storage::disk('public')->delete($newLogoPath);
            }

            throw $e;
        }

        // Dibuang lagi setelah commit: request lain bisa saja mengisi ulang
        // cache dengan nilai lama selagi transaksi berjalan.
        Setting::flushCache();

        if ($oldLogoPath && ($newLogoPath || $removeLogo)) {
            Storage::disk('public')->delete($oldLogoPath);
        }

        AuditLog::record(
            action: 'settings.general.update',
            userId: Auth::id(),
            payload: [
                'keys' => array_keys(array_intersect_key($data, array_flip(self::GENERAL_KEYS))),
                'logo' => $newLogoPath ? 'replaced' : ($removeLogo ? 'removed' : 'unchanged'),
            ],
        );
    }

    /**
     * Simpan pengaturan email. Password kosong = pertahankan yang lama.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateMail(array $data): void
    {
        $passwordChanged = filled($data['mail_password'] ?? null);

        DB::transaction(function () use ($data, $passwordChanged) {
            foreach (self::MAIL_KEYS as $key) {
                Setting::set($key, $data[$key] ?? null);
            }

            if ($passwordChanged) {
                Setting::set('mail_password', Crypt::encryptString($data['mail_password']));
            }
        });

        Setting::flushCache();
        $this->applyMailConfig();

        AuditLog::record(
            action: 'settings.mail.update',
            userId: Auth::id(),
            payload: [
                'mailer' => $data['mail_mailer'] ?? null,
                'host' => $data['mail_host'] ?? null,
                'password_changed' => $passwordChanged,
            ],
        );
    }

    /**
     * Terapkan setting yang tersimpan ke config runtime. Dipanggil sekali di
     * AppServiceProvider::boot, dan lagi setelah setting email disimpan.
     */
    public function applyToConfig(): void
    {
        if ($name = Setting::get('app_name')) {
            config([
                'app.name' => $name,
                'adminlte.title' => $name,
                'adminlte.title_postfix' => ' — '.$name,
                'adminlte.logo' => '<b>'.e($name).'</b>',
            ]);
        }

        // AdminLTE membungkus logo_img dengan asset(), jadi yang diberikan
        // path relatif ke public/ (lewat symlink storage:link).
        if ($logo = Setting::get('app_logo')) {
            config(['adminlte.logo_img' => 'storage/'.$logo]);
        }

        $this->applyMailConfig();
    }

    public function logoUrl(): ?string
    {
        $path = Setting::get('app_logo');

        return $path ? Storage::disk('public')->url($path) : null;
    }

    private function applyMailConfig(): void
    {
        $settings = Setting::allCached();

        // Belum pernah disimpan dari layar Settings → biarkan nilai .env.
        if (blank($settings['mail_mailer'] ?? null)) {
            return;
        }

        config([
            'mail.default' => $settings['mail_mailer'],
            'mail.mailers.smtp.host' => $settings['mail_host'] ?? config('mail.mailers.smtp.host'),
            'mail.mailers.smtp.port' => (int) ($settings['mail_port'] ?? config('mail.mailers.smtp.port')),
            'mail.mailers.smtp.scheme' => $settings['mail_scheme'] ?? null,
            'mail.mailers.smtp.username' => $settings['mail_username'] ?? null,
            'mail.mailers.smtp.password' => $this->decryptPassword($settings['mail_password'] ?? null),
            'mail.from.address' => $settings['mail_from_address'] ?? config('mail.from.address'),
            'mail.from.name' => $settings['mail_from_name'] ?? config('mail.from.name'),
        ]);

        // Mailer yang sudah dibuat memegang config lama — buang supaya request
        // yang sama (mis. tombol email test setelah simpan) memakai nilai baru.
        app('mail.manager')->forgetMailers();
    }

    private function decryptPassword(?string $encrypted): ?string
    {
        if (blank($encrypted)) {
            return null;
        }

        try {
            return Crypt::decryptString($encrypted);
        } catch (DecryptException) {
            // APP_KEY berganti sejak password disimpan — anggap kosong, admin
            // perlu mengisi ulang password SMTP.
            return null;
        }
    }

    private function storeLogo(UploadedFile $logo): string
    {
        // store() mengembalikan false (bukan exception) saat gagal; tanpa
        // pengecekan ini path "0" ikut tersimpan.
        $path = $logo->store(self::LOGO_DIR, 'public');

        if ($path === false) {
            throw new \RuntimeException('Gagal menyimpan logo. Periksa izin tulis folder storage.');
        }

        return $path;
    }
}
