<?php

namespace App\Repositories;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Hash;

class UserRepository
{
    /**
     * Paginate users with filters.
     *
     * @param  array{search?: string, role?: string, status?: string, per_page?: int}  $filters
     */
    public function paginate(array $filters = []): LengthAwarePaginator
    {
        $query = User::query()->with('roles');

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['role'])) {
            $query->role($filters['role']);
        }

        if (! empty($filters['sort']) && ! empty($filters['direction'])) {
            $query->orderBy($filters['sort'], $filters['direction']);
        } else {
            $query->latest();
        }

        return $query->paginate($filters['per_page'] ?? 10);
    }

    /**
     * Find user by ID with roles.
     */
    public function findById(int $id): ?User
    {
        return User::with('roles')->find($id);
    }

    /**
     * Create a user and assign roles.
     */
    public function create(array $data): User
    {
        $userData = array_diff_key($data, array_flip(['roles', 'role']));
        if (isset($userData['password'])) {
            $userData['password'] = Hash::make($userData['password']);
        }

        $user = User::create($userData);

        if (isset($data['roles'])) {
            $user->syncRoles($data['roles']);
        } elseif (isset($data['role'])) {
            $user->assignRole($data['role']);
        }

        return $user;
    }

    /**
     * Update a user and roles.
     */
    public function update(User $user, array $data): User
    {
        $userData = array_diff_key($data, array_flip(['roles', 'role']));
        if (! empty($userData['password'])) {
            $userData['password'] = Hash::make($userData['password']);
        } else {
            unset($userData['password']);
        }

        $user->update($userData);

        if (isset($data['roles'])) {
            $user->syncRoles($data['roles']);
        } elseif (isset($data['role'])) {
            $user->syncRoles([$data['role']]);
        }

        return $user;
    }

    /**
     * Delete a user.
     */
    public function delete(User $user): bool
    {
        return $user->delete();
    }

    /**
     * Reset a user's password.
     */
    public function resetPassword(User $user, string $password): bool
    {
        return $user->update([
            'password' => Hash::make($password),
        ]);
    }

    /**
     * Get total user count.
     */
    public function count(): int
    {
        return User::count();
    }

    /**
     * Get latest users with roles.
     */
    public function getLatest(int $limit = 10)
    {
        return User::with('roles')->latest()->take($limit)->get();
    }
}
