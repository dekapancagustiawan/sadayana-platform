<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Repositories\PermissionRepository;
use App\Services\UserManagement\PermissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Permission;

class PermissionController extends Controller
{
    public function __construct(
        protected PermissionRepository $permissionRepository,
        protected PermissionService $permissionService
    ) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Permission::class);

        if ($request->boolean('grouped')) {
            $permissions = $this->permissionRepository->allGrouped();
        } else {
            $permissions = $this->permissionRepository->all();
        }

        return response()->json($permissions);
    }

    public function show(int $id): JsonResponse
    {
        $permission = $this->permissionRepository->findById($id);

        if (! $permission) {
            return response()->json(['message' => 'Permission not found.'], 404);
        }

        Gate::authorize('view', $permission);

        return response()->json($permission);
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('create', Permission::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:permissions,name'],
            'guard_name' => ['nullable', 'string', 'max:255'],
        ]);

        $permission = $this->permissionService->createPermission($validated);

        return response()->json([
            'message' => 'Permission created successfully.',
            'permission' => $permission,
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $permission = $this->permissionRepository->findById($id);

        if (! $permission) {
            return response()->json(['message' => 'Permission not found.'], 404);
        }

        Gate::authorize('update', $permission);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:permissions,name,'.$permission->id],
            'guard_name' => ['nullable', 'string', 'max:255'],
        ]);

        $updatedPermission = $this->permissionService->updatePermission($permission, $validated);

        return response()->json([
            'message' => 'Permission updated successfully.',
            'permission' => $updatedPermission,
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $permission = $this->permissionRepository->findById($id);

        if (! $permission) {
            return response()->json(['message' => 'Permission not found.'], 404);
        }

        Gate::authorize('delete', $permission);

        $this->permissionService->deletePermission($permission);

        return response()->json(['message' => 'Permission deleted successfully.']);
    }
}
