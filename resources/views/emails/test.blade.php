<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Email test</title>
</head>
<body style="margin:0;padding:24px;background:#f4f6f9;font-family:Helvetica,Arial,sans-serif;color:#333;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
        <tr>
            <td align="center">
                <table role="presentation" width="560" cellspacing="0" cellpadding="0" style="background:#fff;border-radius:6px;padding:32px;">
                    <tr>
                        <td>
                            <h2 style="margin:0 0 16px;">Pengaturan email berfungsi ✔</h2>
                            <p style="margin:0 0 12px;line-height:1.5;">
                                Email ini dikirim dari halaman Settings <strong>{{ config('app.name') }}</strong>
                                oleh {{ $sentBy }} untuk memastikan konfigurasi SMTP sudah benar.
                            </p>
                            <p style="margin:0;color:#888;font-size:13px;">
                                Mailer: {{ config('mail.default') }}
                                @if (config('mail.default') === 'smtp')
                                    · {{ config('mail.mailers.smtp.host') }}:{{ config('mail.mailers.smtp.port') }}
                                @endif
                                · {{ now()->format('d M Y H:i:s') }}
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
