<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Reset password</title>
</head>
<body style="margin:0;padding:24px;background:#f4f6f9;font-family:Helvetica,Arial,sans-serif;color:#333;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
        <tr>
            <td align="center">
                <table role="presentation" width="560" cellspacing="0" cellpadding="0" style="background:#fff;border-radius:6px;padding:32px;">
                    <tr>
                        <td>
                            <h2 style="margin:0 0 16px;">Reset password</h2>
                            <p style="margin:0 0 12px;line-height:1.5;">
                                Halo {{ $user->name }}, kami menerima permintaan reset password untuk akun Anda di
                                <strong>{{ config('app.name') }}</strong>.
                            </p>
                            <p style="margin:24px 0;">
                                <a href="{{ $resetUrl }}"
                                   style="background:#007bff;color:#fff;text-decoration:none;padding:10px 18px;border-radius:4px;display:inline-block;">
                                    Buat password baru
                                </a>
                            </p>
                            <p style="margin:0 0 12px;line-height:1.5;color:#555;">
                                Tautan berlaku {{ $expireMinutes }} menit. Jika Anda tidak meminta reset password,
                                abaikan email ini — password Anda tidak berubah.
                            </p>
                            <p style="margin:0;color:#888;font-size:12px;word-break:break-all;">{{ $resetUrl }}</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
