<?php

namespace App\Repositories;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Spatie\Permission\Models\Permission;

class PermissionRepository
{
    /**
     * Get all permissions.
     *
     * @return Collection<int, Permission>
     */
    public function all(): Collection
    {
        return Permission::all();
    }

    /**
     * Get all permissions grouped by module/prefix (e.g. users, roles, etc.)
     *
     * @return array<string, Collection<int, Permission>>
     */
    public function allGrouped(): array
    {
        return Permission::all()->groupBy(function ($permission) {
            $parts = explode('.', $permission->name);

            return $parts[0] ?? 'general';
        })->toArray();
    }

    /**
     * Paginate permissions.
     */
    public function paginate(int $perPage = 25): LengthAwarePaginator
    {
        return Permission::with('roles')
            ->paginate($perPage);
    }

    /**
     * Find permission by ID.
     */
    public function findById(int $id): ?Permission
    {
        return Permission::find($id);
    }

    /**
     * Create permission.
     */
    public function create(array $data): Permission
    {
        return Permission::create([
            'name' => $data['name'],
            'guard_name' => $data['guard_name'] ?? 'web',
        ]);
    }

    /**
     * Update permission.
     */
    public function update(Permission $permission, array $data): Permission
    {
        $permission->update([
            'name' => $data['name'],
            'guard_name' => $data['guard_name'] ?? $permission->guard_name,
        ]);

        return $permission;
    }

    /**
     * Delete permission.
     */
    public function delete(Permission $permission): bool
    {
        return $permission->delete();
    }
}
