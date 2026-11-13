<?php

namespace App\Services\Auth;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\NewAccessToken;

class AuthService
{
    /**
     * Attempt to authenticate a user and issue a Sanctum token.
     *
     * @param  array{email: string, password: string}  $credentials
     * @return array{token: NewAccessToken, user: User}
     *
     * @throws AuthenticationException
     */
    public function login(array $credentials): array
    {
        if (! Auth::attempt($credentials)) {
            AuditLog::record(
                action: 'auth.login.failed',
                payload: ['email' => $credentials['email']],
            );

            throw new AuthenticationException('Invalid credentials.');
        }

        /** @var User $user */
        $user = Auth::user();

        if (! $user->isActive()) {
            Auth::logout();

            AuditLog::record(
                action: 'auth.login.blocked',
                userId: $user->id,
                payload: ['reason' => 'account_inactive', 'status' => $user->status],
            );

            throw new AuthenticationException('Your account is suspended or inactive.');
        }

        // Revoke all existing tokens to enforce single-session per device (adjust as needed)
        // $user->tokens()->delete();

        $token = $user->createToken('api-token');
        $user->markLoggedIn();

        AuditLog::record(
            action: 'auth.login.success',
            userId: $user->id,
        );

        return [
            'token' => $token,
            'user' => $user->load('roles'),
        ];
    }

    /**
     * Revoke the current user's active token.
     */
    public function logout(User $user): void
    {
        $user->currentAccessToken()->delete();

        AuditLog::record(
            action: 'auth.logout',
            userId: $user->id,
        );
    }
}
