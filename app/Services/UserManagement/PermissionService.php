<?php

namespace App\Services\UserManagement;

use App\Models\AuditLog;
use App\Repositories\PermissionRepository;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;

class PermissionService
{
    public function __construct(
        protected PermissionRepository $permissionRepository
    ) {}

    /**
     * Create permission.
     */
    public function createPermission(array $data): Permission
    {
        $permission = $this->permissionRepository->create($data);

        AuditLog::record(
            action: 'permissions.create',
            userId: Auth::id(),
            payload: [
                'permission_id' => $permission->id,
                'name' => $permission->name,
            ],
            modelType: Permission::class,
            modelId: $permission->id
        );

        return $permission;
    }

    /**
     * Update permission.
     */
    public function updatePermission(Permission $permission, array $data): Permission
    {
        $oldName = $permission->name;
        $permission = $this->permissionRepository->update($permission, $data);

        AuditLog::record(
            action: 'permissions.update',
            userId: Auth::id(),
            payload: [
                'permission_id' => $permission->id,
                'old' => ['name' => $oldName],
                'new' => ['name' => $permission->name],
            ],
            modelType: Permission::class,
            modelId: $permission->id
        );

        return $permission;
    }

    /**
     * Delete permission with protection (cannot delete if it is assigned to roles).
     */
    public function deletePermission(Permission $permission): void
    {
        if ($permission->roles()->count() > 0) {
            throw ValidationException::withMessages([
                'permission' => 'Cannot delete permission because it is assigned to one or more roles.',
            ]);
        }

        $name = $permission->name;
        $this->permissionRepository->delete($permission);

        AuditLog::record(
            action: 'permissions.delete',
            userId: Auth::id(),
            payload: [
                'permission_id' => $permission->id,
                'name' => $name,
            ],
            modelType: Permission::class,
            modelId: $permission->id
        );
    }
}
