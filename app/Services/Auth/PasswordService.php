<?php

namespace App\Services\Auth;

use App\Mail\ResetPasswordMail;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\Mail\MailService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

/**
 * Siklus password (FR-AUTH-003): ganti sendiri, lupa password via email, dan
 * reset lewat token. Password sementara dari admin ditandai lewat
 * User::requirePasswordChange() (lihat UserService).
 */
class PasswordService
{
    public function __construct(private readonly MailService $mail) {}

    /**
     * Ganti password sendiri; menghapus status password sementara.
     */
    public function change(User $user, string $newPassword): void
    {
        $this->storeNewPassword($user, $newPassword);

        AuditLog::record(
            action: 'account.password_changed',
            userId: $user->id,
            modelType: User::class,
            modelId: $user->id,
        );
    }

    /**
     * Kirim tautan reset bila email terdaftar dan akun aktif. Selalu diam
     * terhadap pemanggil (tidak membocorkan email mana yang terdaftar).
     */
    public function sendResetLink(string $email): void
    {
        $email = Str::lower(trim($email));
        $user = User::where('email', $email)->first();

        if ($user === null || ! $user->isActive()) {
            AuditLog::record(action: 'account.password_reset_requested.ignored', payload: ['email' => $email]);

            return;
        }

        Password::broker()->sendResetLink(['email' => $email], function (User $user, string $token) {
            $url = route('password.reset', ['token' => $token, 'email' => $user->email]);

            $this->mail->send($user->email, new ResetPasswordMail($user, $url), 'password_reset');

            AuditLog::record(
                action: 'account.password_reset_requested',
                userId: $user->id,
                modelType: User::class,
                modelId: $user->id,
            );

            return Password::RESET_LINK_SENT;
        });
    }

    /**
     * Reset password lewat token email.
     *
     * @return string status broker (Password::PASSWORD_RESET bila berhasil)
     */
    public function resetWithToken(string $email, string $token, string $newPassword): string
    {
        return Password::broker()->reset(
            ['email' => Str::lower(trim($email)), 'token' => $token, 'password' => $newPassword],
            function (User $user, string $password) {
                $this->storeNewPassword($user, $password);

                AuditLog::record(
                    action: 'account.password_reset',
                    userId: $user->id,
                    modelType: User::class,
                    modelId: $user->id,
                );
            },
        );
    }

    /**
     * remember_token diganti supaya cookie "ingat saya" lama di perangkat
     * lain tidak berlaku lagi.
     */
    private function storeNewPassword(User $user, string $password): void
    {
        $user->forceFill([
            'password' => Hash::make($password),
            'must_change_password' => false,
            'password_changed_at' => now(),
            'remember_token' => Str::random(60),
        ])->save();
    }
}
