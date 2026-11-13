<?php

namespace App\Repositories;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Spatie\Permission\Models\Role;

class RoleRepository
{
    /**
     * Get all roles.
     *
     * @return Collection<int, Role>
     */
    public function all(): Collection
    {
        return Role::with('permissions')->get();
    }

    /**
     * Paginate roles.
     */
    public function paginate(int $perPage = 10, ?string $sort = null, ?string $direction = null): LengthAwarePaginator
    {
        $query = Role::with('permissions')
            ->withCount('users');

        if ($sort && $direction) {
            $query->orderBy($sort, $direction);
        } else {
            $query->latest();
        }

        return $query->paginate($perPage);
    }

    /**
     * Find role by ID.
     */
    public function findById(int $id): ?Role
    {
        return Role::with('permissions')->find($id);
    }

    /**
     * Create role.
     */
    public function create(array $data): Role
    {
        $role = Role::create([
            'name' => $data['name'],
            'guard_name' => $data['guard_name'] ?? 'web',
        ]);

        if (isset($data['permissions'])) {
            $role->syncPermissions($data['permissions']);
        }

        return $role;
    }

    /**
     * Update role.
     */
    public function update(Role $role, array $data): Role
    {
        $role->update([
            'name' => $data['name'],
            'guard_name' => $data['guard_name'] ?? $role->guard_name,
        ]);

        if (isset($data['permissions'])) {
            $role->syncPermissions($data['permissions']);
        }

        return $role;
    }

    /**
     * Delete role.
     */
    public function delete(Role $role): bool
    {
        return $role->delete();
    }

    /**
     * Sync permissions to role.
     */
    public function syncPermissions(Role $role, array $permissions): Role
    {
        $role->syncPermissions($permissions);

        return $role;
    }

    /**
     * Get total role count.
     */
    public function count(): int
    {
        return Role::count();
    }

    /**
     * Get role summary with user counts.
     */
    public function getRoleSummary()
    {
        return Role::withCount('users')->get();
    }
}
