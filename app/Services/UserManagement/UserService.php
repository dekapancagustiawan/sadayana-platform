<?php

namespace App\Services\UserManagement;

use App\Enums\NotificationType;
use App\Enums\UserStatus;
use App\Models\AuditLog;
use App\Models\User;
use App\Repositories\UserRepository;
use App\Services\DashboardNotificationService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class UserService
{
    public function __construct(
        protected UserRepository $userRepository,
        protected DashboardNotificationService $notifications,
    ) {}

    /**
     * Create user.
     */
    public function createUser(array $data): User
    {
        $user = $this->userRepository->create($data);

        AuditLog::record(
            action: 'users.create',
            userId: Auth::id(),
            payload: [
                'user_id' => $user->id,
                'email' => $user->email,
                'roles' => $user->getRoleNames(),
            ],
            modelType: User::class,
            modelId: $user->id
        );

        $this->notifications->notify(
            NotificationType::USER_CREATED,
            'User baru',
            "{$user->name} ({$user->email}) ditambahkan oleh ".(Auth::user()?->name ?? 'sistem').'.',
            route('users.edit', $user),
            $user,
        );

        return $user;
    }

    /**
     * Update user.
     */
    public function updateUser(User $user, array $data): User
    {
        $oldRoles = $user->getRoleNames()->toArray();
        $oldData = $user->only(['name', 'email', 'phone', 'status']);

        $user = $this->userRepository->update($user, $data);

        AuditLog::record(
            action: 'users.update',
            userId: Auth::id(),
            payload: [
                'user_id' => $user->id,
                'old' => array_merge($oldData, ['roles' => $oldRoles]),
                'new' => array_merge(
                    $user->only(['name', 'email', 'phone', 'status']),
                    ['roles' => $user->getRoleNames()->toArray()]
                ),
            ],
            modelType: User::class,
            modelId: $user->id
        );

        return $user;
    }

    /**
     * Delete user with self-deletion protection.
     */
    public function deleteUser(User $user): void
    {
        if ($user->id === Auth::id()) {
            throw ValidationException::withMessages([
                'user' => 'Self-deletion is prohibited.',
            ]);
        }

        $email = $user->email;
        $this->userRepository->delete($user);

        AuditLog::record(
            action: 'users.delete',
            userId: Auth::id(),
            payload: [
                'user_id' => $user->id,
                'email' => $email,
            ],
            modelType: User::class,
            modelId: $user->id
        );
    }

    /**
     * Reset user password.
     */
    public function resetPassword(User $user, string $password): void
    {
        $this->userRepository->resetPassword($user, $password);

        AuditLog::record(
            action: 'users.password_reset',
            userId: Auth::id(),
            payload: [
                'user_id' => $user->id,
            ],
            modelType: User::class,
            modelId: $user->id
        );
    }

    /**
     * Toggle status.
     */
    public function toggleStatus(User $user): User
    {
        if ($user->id === Auth::id()) {
            throw ValidationException::withMessages([
                'user' => 'Self deactivation is prohibited.',
            ]);
        }

        $newStatus = $user->status === UserStatus::Active ? UserStatus::Inactive : UserStatus::Active;
        $user->update(['status' => $newStatus]);

        AuditLog::record(
            action: 'users.toggle_status',
            userId: Auth::id(),
            payload: [
                'user_id' => $user->id,
                'new_status' => $newStatus->value,
            ],
            modelType: User::class,
            modelId: $user->id
        );

        return $user;
    }
}
