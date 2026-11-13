<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * FR-AUTH-003: user dengan password sementara hanya boleh membuka halaman
 * ganti password (dan logout) sampai password-nya diganti.
 */
class EnsurePasswordIsChanged
{
    /** @var list<string> */
    private const ALLOWED_ROUTES = [
        'account.password.edit',
        'account.password.update',
        'logout',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || ! $user->must_change_password || $request->routeIs(...self::ALLOWED_ROUTES)) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Password sementara harus diganti terlebih dahulu.',
            ], 403);
        }

        return redirect()->route('account.password.edit')
            ->with('warning', 'Silakan ganti password sementara Anda sebelum melanjutkan.');
    }
}
