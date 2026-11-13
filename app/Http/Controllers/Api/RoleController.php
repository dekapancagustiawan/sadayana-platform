<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Repositories\RoleRepository;
use App\Services\UserManagement\RoleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    public function __construct(
        protected RoleRepository $roleRepository,
        protected RoleService $roleService
    ) {}

    public function index(): JsonResponse
    {
        Gate::authorize('viewAny', Role::class);

        $roles = $this->roleRepository->all();

        return response()->json($roles);
    }

    public function show(int $id): JsonResponse
    {
        $role = $this->roleRepository->findById($id);

        if (! $role) {
            return response()->json(['message' => 'Role not found.'], 404);
        }

        Gate::authorize('view', $role);

        return response()->json($role);
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('create', Role::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:roles,name'],
            'guard_name' => ['nullable', 'string', 'max:255'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        $role = $this->roleService->createRole($validated);

        return response()->json([
            'message' => 'Role created successfully.',
            'role' => $role->load('permissions'),
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $role = $this->roleRepository->findById($id);

        if (! $role) {
            return response()->json(['message' => 'Role not found.'], 404);
        }

        Gate::authorize('update', $role);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:roles,name,'.$role->id],
            'guard_name' => ['nullable', 'string', 'max:255'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        $updatedRole = $this->roleService->updateRole($role, $validated);

        return response()->json([
            'message' => 'Role updated successfully.',
            'role' => $updatedRole->load('permissions'),
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $role = $this->roleRepository->findById($id);

        if (! $role) {
            return response()->json(['message' => 'Role not found.'], 404);
        }

        Gate::authorize('delete', $role);

        $this->roleService->deleteRole($role);

        return response()->json(['message' => 'Role deleted successfully.']);
    }
}
