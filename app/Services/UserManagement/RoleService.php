<?php

namespace App\Services\UserManagement;

use App\Enums\RoleType;
use App\Models\AuditLog;
use App\Repositories\RoleRepository;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

class RoleService
{
    public function __construct(
        protected RoleRepository $roleRepository
    ) {}

    /**
     * Create role.
     */
    public function createRole(array $data): Role
    {
        $role = $this->roleRepository->create($data);

        AuditLog::record(
            action: 'roles.create',
            userId: Auth::id(),
            payload: [
                'role_id' => $role->id,
                'name' => $role->name,
                'permissions' => $role->permissions->pluck('name')->toArray(),
            ],
            modelType: Role::class,
            modelId: $role->id
        );

        return $role;
    }

    /**
     * Update role.
     */
    public function updateRole(Role $role, array $data): Role
    {
        // Prevent changing super-admin or admin names to something else or modifying their constraints if needed
        $oldName = $role->name;
        $oldPerms = $role->permissions->pluck('name')->toArray();

        $role = $this->roleRepository->update($role, $data);

        AuditLog::record(
            action: 'roles.update',
            userId: Auth::id(),
            payload: [
                'role_id' => $role->id,
                'old' => ['name' => $oldName, 'permissions' => $oldPerms],
                'new' => ['name' => $role->name, 'permissions' => $role->permissions->pluck('name')->toArray()],
            ],
            modelType: Role::class,
            modelId: $role->id
        );

        return $role;
    }

    /**
     * Delete role with guard (can't delete admin roles).
     */
    public function deleteRole(Role $role): void
    {
        if ($role->name === RoleType::Admin->value) {
            throw ValidationException::withMessages([
                'role' => "The role '{$role->name}' is a protected system role and cannot be deleted.",
            ]);
        }

        $name = $role->name;
        $this->roleRepository->delete($role);

        AuditLog::record(
            action: 'roles.delete',
            userId: Auth::id(),
            payload: [
                'role_id' => $role->id,
                'name' => $name,
            ],
            modelType: Role::class,
            modelId: $role->id
        );
    }
}
