<?php

namespace App\Http\Controllers;

use App\Repositories\PermissionRepository;
use App\Repositories\RoleRepository;
use App\Services\UserManagement\PermissionPresenter;
use App\Services\UserManagement\RoleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    public function __construct(
        protected RoleRepository $roleRepository,
        protected PermissionRepository $permissionRepository,
        protected PermissionPresenter $permissionPresenter,
        protected RoleService $roleService
    ) {}

    /**
     * Display a list of roles.
     */
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Role::class);

        $perPage = $request->integer('per_page', 10);
        $perPage = in_array($perPage, [5, 10, 25, 50, 100]) ? $perPage : 10;

        $allowedSort = ['name', 'created_at'];
        $sort = $request->input('sort', 'created_at');
        $direction = $request->input('direction', 'desc');

        if (! in_array($sort, $allowedSort)) {
            $sort = 'created_at';
        }
        $direction = strtolower($direction) === 'asc' ? 'asc' : 'desc';

        $roles = $this->roleRepository->paginate($perPage, $sort, $direction);

        return view('roles.index', compact('roles'));
    }

    /**
     * Show the form for creating a new role.
     */
    public function create(): View
    {
        Gate::authorize('create', Role::class);

        $permissionGroups = $this->permissionPresenter->groups($this->permissionRepository->all());
        $presenter = $this->permissionPresenter;

        return view('roles.create', compact('permissionGroups', 'presenter'));
    }

    /**
     * Store a newly created role.
     */
    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', Role::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:roles,name'],
            'guard_name' => ['required', 'string', 'max:255'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        $role = $this->roleService->createRole($validated);

        return redirect()->route('roles.index')
            ->with('success', "Role \"{$role->name}\" created successfully.");
    }

    /**
     * Show the form for editing a role.
     */
    public function edit(Role $role): View
    {
        Gate::authorize('view', $role);

        $permissionGroups = $this->permissionPresenter->groups($this->permissionRepository->all());
        $presenter = $this->permissionPresenter;
        $role->load('permissions');

        return view('roles.edit', compact('role', 'permissionGroups', 'presenter'));
    }

    /**
     * Update the specified role.
     */
    public function update(Request $request, Role $role): RedirectResponse
    {
        Gate::authorize('update', $role);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:roles,name,'.$role->id],
            'guard_name' => ['required', 'string', 'max:255'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        $this->roleService->updateRole($role, $validated);

        return redirect()->route('roles.index')
            ->with('success', "Role \"{$role->name}\" updated successfully.");
    }

    /**
     * Delete the specified role.
     */
    public function destroy(Role $role): RedirectResponse
    {
        Gate::authorize('delete', $role);

        $name = $role->name;
        try {
            $this->roleService->deleteRole($role);

            return redirect()->route('roles.index')
                ->with('success', "Role \"{$name}\" deleted successfully.");
        } catch (\Exception $e) {
            return redirect()->route('roles.index')
                ->with('error', $e->getMessage());
        }
    }
}
