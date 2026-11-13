# Laravel Base

Template awal proyek Laravel 13 yang sudah berisi **landing page** dan **back office admin** (AdminLTE) lengkap dengan manajemen akses, audit log, pengaturan aplikasi, dan API. Tinggal tambahkan modul bisnis Anda.

## Fitur

**Landing page publik**
- Section Hero, Fitur, Tentang, Testimoni, FAQ, Kontak — urutan & visibilitas diatur dari config
- Dua bahasa (ID/EN) dengan pengalih bahasa berbasis session
- Nama, logo, kontak, WhatsApp, dan sosial media diambil dari layar Settings
- Form kontak → email ke admin + notifikasi dashboard, dilindungi reCAPTCHA v3, honeypot, dan rate limit
- Tailwind CSS 4 (Vite) + Alpine.js, tema warna di satu tempat

**Back office (AdminLTE)**
- Login admin dengan path yang bisa dirahasiakan (`ADMIN_LOGIN_PATH`) + reCAPTCHA opsional
- Dashboard ringkas (statistik user/role, aktivitas terbaru)
- Manajemen User, Role, dan Permission (Spatie) — izin menu bisa diatur per role dari UI tanpa ubah kode
- Audit Log untuk login dan setiap perubahan penting
- General Settings: identitas aplikasi + konfigurasi SMTP dari UI (password terenkripsi), tombol kirim email test
- Email Log: setiap email yang dikirim tercatat (terkirim/gagal + pesan error)
- Notifikasi dashboard (lonceng navbar) dengan helper `notify()`
- Halaman error (401/403/404/419/429/500/503) yang mengarahkan user ke tempat yang benar

**API (Laravel Sanctum)**
- `POST /api/auth/login`, `POST /api/auth/logout`, `GET /api/auth/me`
- CRUD `/api/users`, `/api/roles`, `/api/permissions`
- `GET /api/health`

## Kebutuhan

- PHP 8.3+ (disarankan 8.4; ekstensi: pdo_sqlite atau pdo_pgsql/pdo_mysql, mbstring, openssl, fileinfo, gd, intl, zip)
- Composer 2
- Node.js 22+
- SQLite (default), PostgreSQL, atau MySQL

## Mulai

```bash
git clone <repo-ini> nama-proyek
cd nama-proyek
composer setup      # install, .env, key, migrate + seed, storage:link, build aset
composer dev        # server + queue + log + vite
```

Buka `http://localhost:8000` untuk landing page dan `http://localhost:8000/login` untuk admin.

| Akun | Email | Password |
|---|---|---|
| Admin | `admin@example.com` | `password` |
| User | `user@example.com` | `password` |

> Akun demo ini hanya dibuat di luar production. Dengan `APP_ENV=production`, seeder hanya membuat satu admin dari `SEED_ADMIN_EMAIL`/`SEED_ADMIN_PASSWORD` (password acak bila kosong).

Setelah login, buka **Settings → General Settings** untuk mengisi nama aplikasi, logo, kontak, dan SMTP.

## Konfigurasi `.env` penting

| Variabel | Fungsi |
|---|---|
| `APP_NAME` | Nama default (ditimpa oleh nama di General Settings) |
| `APP_LOCALE` | Bahasa default landing: `id` atau `en` |
| `ADMIN_LOGIN_PATH` | URL form login admin, default `login`. Ganti di production, mis. `panel-masuk-x7` |
| `NOCAPTCHA_SITEKEY` / `NOCAPTCHA_SECRET` | Key reCAPTCHA **v3**. Kosong = captcha mati |
| `NOCAPTCHA_ENABLED` | Matikan captcha walau key terisi (`false`) |
| `SEED_ADMIN_EMAIL` / `SEED_ADMIN_PASSWORD` | Akun admin awal saat `db:seed` di production. Password kosong = dibuat acak & ditampilkan sekali |
| `SANCTUM_EXPIRATION` | Masa berlaku token API dalam menit (default `10080` = 7 hari) |
| `LANDING_HERO_IMAGE` | Gambar hero, path relatif ke `public/` |
| `MAIL_*` | Default email; setelah disimpan dari General Settings, nilai dari UI yang dipakai |

## Menyesuaikan landing page

| Ingin mengubah… | Edit |
|---|---|
| Section yang tampil & urutannya, ikon fitur, angka statistik | `config/landing.php` |
| Semua teks (ID & EN) | `lang/id/landing.php`, `lang/en/landing.php` |
| Warna tema (`brand-*`, `accent`) | `resources/css/app.css` (blok `@theme`) |
| Tampilan satu section | `resources/views/landing/sections/{nama}.blade.php` |
| Section baru | buat `resources/views/landing/sections/{nama}.blade.php`, tambahkan `{nama}` ke `config/landing.php` |
| Nama, logo, kontak, sosmed | UI: Settings → General Settings |

Setelah mengubah warna atau class Tailwind, jalankan `npm run build` (atau biarkan `composer dev` berjalan).

## Menambah modul baru

Contoh: modul **Produk**.

1. **Migration** — pakai `$table->auditFields()` untuk kolom `created_by/updated_by/deleted_by`:
   ```php
   Schema::create('products', function (Blueprint $table) {
       $table->uuid('id')->primary();
       $table->string('code')->unique();
       $table->string('name');
       $table->timestamps();
       $table->softDeletes();
       $table->auditFields();
   });
   ```
2. **Model** — `use HasUuids, SoftDeletes, Blameable;` (trait `App\Traits\Blameable` mengisi kolom audit otomatis).
3. **Service** di `app/Services/...` — semua logika tulis di sini, bungkus dengan `DB::transaction`, catat dengan `AuditLog::record(...)`. Kode berurutan (`PRD290926001`) dari `CodeGeneratorService::generate('PRD', 'products', 'code')`.
4. **FormRequest** di `app/Http/Requests` + **Controller** tipis yang memanggil service.
5. **Izin menu** — tambahkan `'access-products' => [$admin, ...]` di `database/seeders/MenuAccessPermissionsSeeder.php`, label di `PermissionPresenter::MENU_LABELS`, dan baris di `MENU_ACCESS_BASELINE` pada `tests/Feature/RoleMenuAccessTest.php`.
6. **Route** di `routes/web.php` dengan `->middleware('can:access-products')`.
7. **Menu** di `config/adminlte.php` dengan `'can' => 'access-products'` (+ terjemahan di `lang/id/menu.php`).
8. Jalankan `php artisan db:seed --class=MenuAccessPermissionsSeeder` lalu `composer test`.

Menu dan URL selalu dipagari izin yang sama — test `RouteAuthorizationMatrixTest` menjaga hal ini.

## Helper yang tersedia

```php
// Notifikasi lonceng dashboard
app(DashboardNotificationService::class)->notify(NotificationType::INFO, 'Judul', 'Pesan', $url, $model);

// Kirim email + catat ke Email Log (tidak melempar exception saat SMTP gagal)
$log = app(MailService::class)->send('a@b.com', new SomeMailable(...), 'jenis_email');
$log->status; // 'sent' | 'failed'

// Pengaturan key-value (di-cache)
Setting::get('contact_email', 'default@example.com');
Setting::set('some_key', 'value');

// Audit log
AuditLog::record(action: 'products.create', userId: auth()->id(), payload: [...]);

// Captcha di form mana pun
@include('partials.recaptcha', ['action' => 'nama_form'])   // blade
...Captcha::rules()                                          // rules validasi
```

## Otorisasi

- **Role** Admin (superuser lewat `Gate::before`) dan User. Role lain dibuat bebas dari layar Roles.
- **Izin menu** `access-*` disimpan di database (Spatie). Centang/hapus izin per role dari UI tanpa deploy.
- **Izin fungsional** (`users.view`, `roles.edit`, …) dibuat oleh `PermissionSeeder`.

| Izin | Default | Membuka |
|---|---|---|
| `access-dashboard` | Admin, User | Dashboard |
| `access-notifications` | Admin | Lonceng notifikasi |
| `access-user-management` | Admin | Users, Roles & Permissions |
| `access-audit-logs` | Admin | Audit Log |
| `access-settings` | Admin | General Settings, Email Log |

## Testing

```bash
composer test                               # seluruh test (SQLite in-memory)
php artisan test --filter=LandingPageTest
vendor/bin/pint                             # format kode
```

GitHub Actions (`.github/workflows/ci.yml`) menjalankan Pint, `composer audit`, dan seluruh test di PHP 8.3 & 8.4, plus `npm run build`, pada setiap push ke `main` dan pull request.

## Checklist production

**Keamanan**

- [ ] `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL` benar (`https://…`), `LOG_LEVEL=warning`
- [ ] `ADMIN_LOGIN_PATH` diganti ke path yang tidak mudah ditebak
- [ ] Akun admin awal: di production `db:seed` **tidak** membuat akun demo; isi `SEED_ADMIN_EMAIL`/`SEED_ADMIN_PASSWORD` atau catat password acak yang ditampilkan. Bila database disalin dari development, ganti password / hapus akun demo
- [ ] Key reCAPTCHA v3 diisi
- [ ] Situs dilayani lewat HTTPS; `SESSION_SECURE_COOKIE=true`, `SESSION_ENCRYPT=true`
- [ ] `SANCTUM_EXPIRATION` sesuai kebijakan (default 7 hari) dan scheduler berjalan (`* * * * * php artisan schedule:run`) untuk `sanctum:prune-expired`
- [ ] `composer audit` dan `npm audit` bersih

Login web & API sudah dibatasi (5 percobaan/menit per email+IP, 20/menit per IP) dan setiap respons membawa header keamanan dasar (`App\Http\Middleware\SecurityHeaders`; HSTS hanya lewat HTTPS).

**Performa**

- [ ] Database PostgreSQL/MySQL (SQLite hanya untuk development / trafik sangat kecil)
- [ ] Redis untuk `CACHE_STORE`, `SESSION_DRIVER`, `QUEUE_CONNECTION` bila trafik bertambah
- [ ] OPcache aktif di PHP
- [ ] `php artisan optimize` (cache config, route, view, event) + `npm run build` di setiap deploy

**Lainnya**

- [ ] SMTP diisi dari General Settings, lalu kirim email test
- [ ] `php artisan storage:link` (logo disimpan di disk `public`)
- [ ] Queue worker berjalan bila `QUEUE_CONNECTION` bukan `sync`

## Struktur singkat

```
app/
  Http/Controllers/         Admin (User/Role/Permission/AuditLog/Dashboard)
  Http/Controllers/Api/     API Sanctum + health
  Http/Controllers/Public/  Landing & form kontak
  Http/Controllers/Settings/ General Settings & Email Log
  Services/                 Logika bisnis (Settings, Mail, UserManagement, Dashboard, …)
  Repositories/             Hanya untuk user/role/permission
  Support/Captcha.php       Saklar reCAPTCHA
config/landing.php          Struktur landing page
resources/views/landing/    Landing (partials + sections)
resources/views/errors/     Halaman error tanpa ketergantungan Vite
```

## Lisensi

MIT
