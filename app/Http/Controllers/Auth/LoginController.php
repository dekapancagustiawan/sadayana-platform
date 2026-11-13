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
        // FR-AUTH-001: identitas login berupa email ATAU nomor HP.
        $validated = $request->validate([
            'login' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
            ...Captcha::rules(),
        ], Captcha::messages());

        $credentials = User::credentialsFor($validated['login'], $validated['password']);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            AuditLog::record(
                action: 'web.login.failed',
                payload: ['login' => $validated['login']],
            );

            return back()
                ->withInput($request->only('login', 'remember'))
                ->withErrors(['login' => 'Email/nomor HP atau password salah.']);
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
                ->withInput($request->only('login'))
                ->withErrors(['login' => 'Akun Anda nonaktif atau ditangguhkan. Hubungi pengurus.']);
        }

        $request->session()->regenerate();
        $user->markLoggedIn();

        AuditLog::record(
            action: 'web.login.success',
            userId: $user->id,
        );

        // Password sementara wajib diganti dulu (FR-AUTH-003); middleware
        // EnsurePasswordIsChanged juga menahan halaman lain sampai selesai.
        if ($user->must_change_password) {
            session()->forget('url.intended');

            return redirect()->route('account.password.edit');
        }

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
