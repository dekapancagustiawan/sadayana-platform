<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Support\Captcha;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class LoginController extends Controller
{
    /**
     * Show the login form.
     *
     * GET /{app.login_path}
     */
    public function showLoginForm(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route(Auth::user()->landingRoute());
        }

        return view('auth.login');
    }

    /**
     * Handle a login request.
     *
     * POST /{app.login_path}
     */
    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
            ...Captcha::rules(),
        ], Captcha::messages());
        unset($credentials[Captcha::FIELD]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            AuditLog::record(
                action: 'web.login.failed',
                payload: ['email' => $credentials['email']],
            );

            return back()
                ->withInput($request->only('email', 'remember'))
                ->withErrors(['email' => 'These credentials do not match our records.']);
        }

        /** @var User $user */
        $user = Auth::user();

        if (! $user->isActive()) {
            Auth::logout();

            AuditLog::record(
                action: 'web.login.blocked',
                userId: $user->id,
                payload: ['status' => $user->status->value],
            );

            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => 'Your account is suspended or inactive. Please contact your administrator.']);
        }

        $request->session()->regenerate();
        $user->markLoggedIn();

        AuditLog::record(
            action: 'web.login.success',
            userId: $user->id,
        );

        $landingRoute = $user->landingRoute();

        // Halaman utama dashboard tetap menghormati `url.intended` (mis. user
        // membuka URL dalam lalu diminta login); role lain dipaksa ke halaman
        // yang memang boleh ia buka.
        if ($landingRoute !== 'dashboard') {
            session()->forget('url.intended');

            return redirect()->route($landingRoute);
        }

        return redirect()->intended(route('dashboard'));
    }

    /**
     * Log the user out.
     *
     * POST /logout
     */
    public function logout(Request $request): RedirectResponse
    {
        $userId = Auth::id();

        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        AuditLog::record(
            action: 'web.logout',
            userId: $userId,
        );

        return redirect()->route('login');
    }
}
