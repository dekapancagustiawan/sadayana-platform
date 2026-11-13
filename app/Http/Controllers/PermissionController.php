<?php

namespace App\Http\Controllers;

use App\Repositories\PermissionRepository;
use App\Services\UserManagement\PermissionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Spatie\Permission\Models\Permission;

class PermissionController extends Controller
{
    public function __construct(
        protected PermissionRepository $permissionRepository,
        protected PermissionService $permissionService
    ) {}

    /**
     * Display a list of permissions.
     */
    public function index(): View
    {
        Gate::authorize('viewAny', Permission::class);

        $permissions = $this->permissionRepository->paginate(25);

        return view('permissions.index', compact('permissions'));
    }

    /**
     * Show the form for creating a new permission.
     */
    public function create(): View
    {
        Gate::authorize('create', Permission::class);

        return view('permissions.create');
    }

    /**
     * Store a newly created permission.
     */
    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', Permission::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:permissions,name'],
            'guard_name' => ['required', 'string', 'max:255'],
        ]);

        $permission = $this->permissionService->createPermission($validated);

        return redirect()->route('permissions.index')
            ->with('success', "Permission \"{$permission->name}\" created successfully.");
    }

    /**
     * Show the form for editing a permission.
     */
    public function edit(Permission $permission): View
    {
        Gate::authorize('view', $permission);

        return view('permissions.edit', compact('permission'));
    }

    /**
     * Update the specified permission.
     */
    public function update(Request $request, Permission $permission): RedirectResponse
    {
        Gate::authorize('update', $permission);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:permissions,name,'.$permission->id],
            'guard_name' => ['required', 'string', 'max:255'],
        ]);

        $this->permissionService->updatePermission($permission, $validated);

        return redirect()->route('permissions.index')
            ->with('success', "Permission \"{$permission->name}\" updated successfully.");
    }

    /**
     * Delete the specified permission.
     */
    public function destroy(Permission $permission): RedirectResponse
    {
        Gate::authorize('delete', $permission);

        $name = $permission->name;
        try {
            $this->permissionService->deletePermission($permission);

            return redirect()->route('permissions.index')
                ->with('success', "Permission \"{$name}\" deleted successfully.");
        } catch (\Exception $e) {
            return redirect()->route('permissions.index')
                ->with('error', $e->getMessage());
        }
    }
}
