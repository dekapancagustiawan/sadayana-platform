<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applies the visitor's chosen language (session-based, no URL prefix — see
 * routes/web.php's /lang/{locale} switch route) to every request. Only the
 * public landing page exposes a language switcher; the admin side is written
 * in Indonesian/English mix, but this middleware is registered globally on
 * the web group so it's harmless either way — it falls back to APP_LOCALE
 * when nothing has been chosen.
 */
class SetLocale
{
    public const SUPPORTED_LOCALES = ['id', 'en'];

    /** Dipakai bila APP_LOCALE bukan salah satu SUPPORTED_LOCALES. */
    public const FALLBACK_LOCALE = 'id';

    public function handle(Request $request, Closure $next): Response
    {
        $default = self::defaultLocale();
        $locale = session('locale', $default);

        if (! in_array($locale, self::SUPPORTED_LOCALES, true)) {
            $locale = $default;
        }

        app()->setLocale($locale);

        return $next($request);
    }

    public static function defaultLocale(): string
    {
        $configured = config('app.locale');

        return in_array($configured, self::SUPPORTED_LOCALES, true) ? $configured : self::FALLBACK_LOCALE;
    }
}
