<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Services\Auth\PasswordService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * Ganti password sendiri (FR-AUTH-003). Terbuka untuk semua user login —
 * tidak dipagari gate menu.
 */
class PasswordController extends Controller
{
    public function __construct(private readonly PasswordService $passwords) {}

    public function edit(Request $request): View
    {
        return view('account.password', [
            'mustChange' => (bool) $request->user()->must_change_password,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', 'different:current_password', Password::min(8)->letters()->numbers()],
        ], [
            'current_password.current_password' => 'Password saat ini salah.',
            'password.different' => 'Password baru harus berbeda dari password saat ini.',
        ]);

        $user = $request->user();
        $this->passwords->change($user, $validated['password']);

        // Sesi lain milik user ini tidak lagi sah setelah password diganti.
        $request->session()->regenerate();

        return redirect()->route($user->landingRoute())
            ->with('success', 'Password berhasil diganti.');
    }
}
