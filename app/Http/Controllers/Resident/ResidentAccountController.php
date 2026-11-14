<?php

namespace App\Http\Controllers\Resident;

use App\Http\Controllers\Controller;
use App\Models\Resident;
use App\Models\User;
use App\Services\Resident\ResidencyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use RuntimeException;

/**
 * Akun warga untuk penghuni (FR-RES-005): buat akun baru dengan password
 * sementara, hubungkan akun yang sudah ada, atau putuskan hubungan.
 */
class ResidentAccountController extends Controller
{
    public function __construct(private readonly ResidencyService $residency) {}

    public function store(Resident $resident): RedirectResponse
    {
        Gate::authorize('residents.accounts');

        try {
            $result = $this->residency->createAccountFor($resident);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        $login = $result['user']->email ?? $result['user']->phone;

        // Password sementara ditampilkan SEKALI untuk diserahkan ke warga.
        return redirect()->route('residents.show', $resident)
            ->with('success', 'Akun warga dibuat.')
            ->with('temporary_password', ['login' => $login, 'password' => $result['password']]);
    }

    public function link(Request $request, Resident $resident): RedirectResponse
    {
        Gate::authorize('residents.accounts');

        $validated = $request->validate(['login' => ['required', 'string', 'max:255']], [], ['login' => 'email / nomor HP akun']);

        $credentials = User::credentialsFor($validated['login'], '');
        unset($credentials['password']);
        $user = User::where($credentials)->first();

        if (! $user) {
            return back()->withInput()->with('error', 'Akun dengan email/nomor HP tersebut tidak ditemukan.');
        }

        try {
            $this->residency->linkAccount($resident, $user);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Akun {$user->name} terhubung ke penghuni ini.");
    }

    public function destroy(Resident $resident, User $user): RedirectResponse
    {
        Gate::authorize('residents.accounts');

        $this->residency->unlinkAccount($resident, $user);

        return back()->with('success', "Hubungan akun {$user->name} diputus. Akun tidak dihapus.");
    }
}
