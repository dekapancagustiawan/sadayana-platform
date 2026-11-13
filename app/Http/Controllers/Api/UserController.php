<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Repositories\UserRepository;
use App\Services\UserManagement\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function __construct(
        protected UserRepository $userRepository,
        protected UserService $userService
    ) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', User::class);

        $filters = $request->only(['search', 'role', 'status', 'per_page']);
        $users = $this->userRepository->paginate($filters);

        return response()->json($users);
    }

    public function show(int $id): JsonResponse
    {
        $user = $this->userRepository->findById($id);

        if (! $user) {
            return response()->json(['message' => 'User not found.'], 404);
        }

        Gate::authorize('view', $user);

        return response()->json($user);
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('create', User::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:20'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'status' => ['required', 'string', Rule::in(['active', 'inactive', 'suspended'])],
            'roles' => ['required', 'array'],
            'roles.*' => ['string', 'exists:roles,name'],
        ]);

        $user = $this->userService->createUser($validated);

        return response()->json([
            'message' => 'User created successfully.',
            'user' => $user->load('roles'),
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $user = $this->userRepository->findById($id);

        if (! $user) {
            return response()->json(['message' => 'User not found.'], 404);
        }

        Gate::authorize('update', $user);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:20'],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'status' => ['required', 'string', Rule::in(['active', 'inactive', 'suspended'])],
            'roles' => ['required', 'array'],
            'roles.*' => ['string', 'exists:roles,name'],
        ]);

        $updatedUser = $this->userService->updateUser($user, $validated);

        return response()->json([
            'message' => 'User updated successfully.',
            'user' => $updatedUser->load('roles'),
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $user = $this->userRepository->findById($id);

        if (! $user) {
            return response()->json(['message' => 'User not found.'], 404);
        }

        Gate::authorize('delete', $user);

        $this->userService->deleteUser($user);

        return response()->json(['message' => 'User deleted successfully.']);
    }

    public function toggleStatus(int $id): JsonResponse
    {
        $user = $this->userRepository->findById($id);

        if (! $user) {
            return response()->json(['message' => 'User not found.'], 404);
        }

        Gate::authorize('update', $user);

        $updatedUser = $this->userService->toggleStatus($user);

        return response()->json([
            'message' => 'User status toggled successfully.',
            'user' => $updatedUser,
        ]);
    }

    public function resetPassword(Request $request, int $id): JsonResponse
    {
        $user = $this->userRepository->findById($id);

        if (! $user) {
            return response()->json(['message' => 'User not found.'], 404);
        }

        Gate::authorize('resetPassword', $user);

        $validated = $request->validate([
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $this->userService->resetPassword($user, $validated['password']);

        return response()->json(['message' => 'Password reset successfully.']);
    }
}
