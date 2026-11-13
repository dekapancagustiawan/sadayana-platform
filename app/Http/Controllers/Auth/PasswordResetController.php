<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Auth\PasswordService;
use App\Support\Captcha;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password as PasswordBroker;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * Lupa password lewat email (FR-AUTH-003). Route-nya berada di bawah
 * ADMIN_LOGIN_PATH supaya tidak membocorkan alamat login lewat URL publik.
 * Akun tanpa email direset oleh admin dari layar Users.
 */
class PasswordResetController extends Controller
{
    public function __construct(private readonly PasswordService $passwords) {}

    public function request(): View
    {
        return view('auth.forgot-password');
    }

    public function email(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            ...Captcha::rules(),
        ], Captcha::messages());

        $this->passwords->sendResetLink($validated['email']);

        // Pesan sama untuk email terdaftar maupun tidak (anti enumerasi).
        return back()->with('status', 'Jika email tersebut terdaftar dan aktif, tautan reset password sudah dikirim. Periksa kotak masuk Anda.');
    }

    public function edit(Request $request, string $token): View
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => (string) $request->query('email', ''),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ]);

        $status = $this->passwords->resetWithToken($validated['email'], $validated['token'], $validated['password']);

        if ($status !== PasswordBroker::PASSWORD_RESET) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => 'Tautan reset tidak valid atau sudah kedaluwarsa. Silakan minta tautan baru.']);
        }

        return redirect()->route('login')->with('status', 'Password berhasil direset. Silakan masuk dengan password baru.');
    }
}
