<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\LoginRequest;
use App\Models\User;
use App\Services\Auth\AuthService;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(
        private readonly AuthService $authService,
    ) {}

    /**
     * Handle user login — returns a Sanctum API token.
     *
     * POST /api/auth/login
     */
    public function login(LoginRequest $request): JsonResponse
    {
        try {
            $result = $this->authService->login(
                User::credentialsFor($request->loginIdentifier(), (string) $request->input('password')),
            );

            return response()->json([
                'status' => 'ok',
                'message' => 'Login successful.',
                'data' => [
                    'token' => $result['token']->plainTextToken,
                    'token_type' => 'Bearer',
                    'user' => [
                        'id' => $result['user']->id,
                        'name' => $result['user']->name,
                        'email' => $result['user']->email,
                        'phone' => $result['user']->phone,
                        'status' => $result['user']->status,
                        'must_change_password' => $result['user']->must_change_password,
                        'roles' => $result['user']->getRoleNames(),
                    ],
                ],
            ]);
        } catch (AuthenticationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 401);
        }
    }

    /**
     * Revoke the current access token (logout).
     *
     * POST /api/auth/logout
     */
    public function logout(Request $request): JsonResponse
    {
        $this->authService->logout($request->user());

        return response()->json([
            'status' => 'ok',
            'message' => 'Logged out successfully.',
        ]);
    }

    /**
     * Return the currently authenticated user with roles and permissions.
     *
     * GET /api/auth/me
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'status' => 'ok',
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'status' => $user->status,
                'roles' => $user->getRoleNames(),
                'permissions' => $user->getAllPermissions()->pluck('name'),
            ],
        ]);
    }
}
