# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project

**Laravel Base** — a Laravel 13 / PHP 8.3+ starter: public landing page + AdminLTE back office (users, roles & permissions, audit log, general settings incl. SMTP, email log, dashboard notifications) and a Sanctum API for user management. Business modules are meant to be added on top; see "Adding a module" below.

## Commands

```bash
composer setup                 # install deps, .env, sqlite file, key, migrate --seed, storage:link, npm build
composer dev                   # serve + queue:listen + pail + vite, concurrently
composer test                  # config:clear then artisan test
php artisan test --filter=SettingsTest
vendor/bin/pint                # formatting (Laravel Pint)
php artisan db:seed --class=MenuAccessPermissionsSeeder
```

`.env.example` defaults to **SQLite**; `phpunit.xml` forces sqlite `:memory:`. Migrations and raw SQL must work on SQLite, PostgreSQL and MySQL.

## Architecture

**Request flow (web):** Route → FormRequest (`app/Http/Requests`) → thin Controller → Service (`app/Services/**`) → Eloquent model → Blade view. Multi-write logic lives in the service inside `DB::transaction`; services throw `\RuntimeException` and controllers turn it into `redirect()->back()->with('error', …)`.

Repositories (`app/Repositories`) exist **only** for user/role/permission management. Other domains go controller → service directly.

**Authorization — keep all layers in sync when adding a screen:**
1. `Gate::before` in `AppServiceProvider` grants the `Admin` role everything.
2. Menu gates `access-*` are **database permissions** (Spatie resolves any ability name), seeded by `MenuAccessPermissionsSeeder`. There are no `Gate::define` closures. A new screen needs: the permission in that seeder, a label in `PermissionPresenter::MENU_LABELS`, a row in `RoleMenuAccessTest::MENU_ACCESS_BASELINE`, a `can:access-…` route middleware, and a `config/adminlte.php` menu entry with the same `'can'`. `RoleMenuAccessTest` fails if a menu gate is missing from the baseline; `RouteAuthorizationMatrixTest` checks URLs are closed when the menu is hidden.
3. Fine-grained permissions (`users.view`, `roles.edit`, …) come from `PermissionSeeder` and are used as route middleware.

Roles in code are only `App\Enums\RoleType::{Admin, User}`; other roles are created from the UI. Compare with `RoleType::X->value`. `User::landingRoute()` maps permissions → first page after login (also used by error pages).

**Models:** new domain models use UUID keys (`HasUuids`) + `SoftDeletes` + `App\Traits\Blameable`; migrations declare audit columns with the `$table->auditFields()` Blueprint macro (registered in `AppServiceProvider::register()`). Human-facing codes come from `CodeGeneratorService::generate($prefix, $table, $column)` (row-locked `code_sequences`, resets monthly) — never build them by hand.

**Settings:** `App\Models\Setting` is a cached key-value store (`Setting::get/set`, cache flushed on write, returns defaults if the table doesn't exist yet). `SettingsService::applyToConfig()` runs in `AppServiceProvider::boot()` and overrides `app.name`, AdminLTE title/logo, and `mail.*` from saved settings. The SMTP password is stored with `Crypt::encryptString` and must never be rendered or written to the audit log. `SettingsService::publicProfile()` is the only settings payload that may reach public views.

**Email:** send through `App\Services\Mail\MailService::send($to, $mailable, $type)` — it logs every attempt to `email_logs` and swallows transport exceptions (check the returned `EmailLog->status`). Don't call `Mail::to()->send()` directly for app emails.

**Notifications:** `DashboardNotificationService::notify($type, $title, $message, $url, $reference)`. Notifications are global (not per user), gated by `access-notifications`.

**Captcha:** `App\Support\Captcha::enabled()` decides (config `captcha.enabled` + both keys present). Forms include `@include('partials.recaptcha', ['action' => '…'])` and merge `...Captcha::rules()` into validation — the rule must be unconditional when enabled (never only when the field is present).

**Auth:** session `web` guard for the admin UI (login URL = `config('app.login_path')` from `ADMIN_LOGIN_PATH`) and `auth:sanctum` for `/api/*`. Guests hitting internal URLs get a 401 page, not a redirect to login, and public pages/error pages never link to the login URL. `bootstrap/app.php` renders auth/authorization/validation exceptions as JSON for API requests and registers the global `SetLocale` and `SecurityHeaders` middleware. Route authorization uses only the built-in `can:<permission>` middleware (it goes through the Gate, so Admin stays superuser) — there are no `role:`/`permission:` aliases. Login (web + API) uses the `throttle:login` limiter defined in `AppServiceProvider`.

**Frontend is split in two:**
- Admin: Blade extending `adminlte::page`; jQuery/AdminLTE assets are vendored in `public/vendor`. Each view renders its own flash messages.
- Public landing (`resources/views/landing/**`, `layouts/landing.blade.php`): Tailwind 4 via Vite + Alpine/AOS/Phosphor from CDNs. Sections are listed in `config/landing.php`; unknown section names are skipped. Colours come from the `brand-*`/`accent` tokens in `resources/css/app.css` — don't hard-code hex colours in landing partials. Copy lives in `lang/{id,en}/landing.php`.
- Error pages (`resources/views/errors/layout.blade.php`) deliberately avoid `@vite` and wrap every DB/session read in `rescue()`.

**i18n:** session-based locale (`/lang/{locale}`, default from `APP_LOCALE`, supported `id`/`en`) applied by `SetLocale`. Only the landing page is translated; admin screens are mostly English with Indonesian menu labels via `lang/id/menu.php`. Code comments are in Indonesian — match the surrounding language.

## Notes

- `UploadedFile::store()` returns `false` (not an exception) on failure — check it and throw (see `SettingsService::storeLogo()`). Uploaded images: no SVG.
- Tests use `RefreshDatabase` and run seeders inline (`$this->artisan('db:seed', ['--class' => 'PermissionSeeder'])`, then `RoleSeeder`, `MenuAccessPermissionsSeeder`). `Tests\TestCase` calls `withoutVite()`. Fake captcha with `NoCaptcha::shouldReceive('verifyResponse')`.
- Commit messages follow `feat: …` / `fix: …`.
