<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Pesan kontak</title>
</head>
<body style="margin:0;padding:24px;background:#f4f6f9;font-family:Helvetica,Arial,sans-serif;color:#333;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
        <tr>
            <td align="center">
                <table role="presentation" width="560" cellspacing="0" cellpadding="0" style="background:#fff;border-radius:6px;padding:32px;">
                    <tr>
                        <td>
                            <h2 style="margin:0 0 20px;">Pesan baru dari form kontak</h2>
                            <table role="presentation" cellspacing="0" cellpadding="0" style="font-size:14px;line-height:1.6;">
                                <tr><td style="padding-right:16px;color:#888;">Nama</td><td>{{ $contact['name'] }}</td></tr>
                                <tr><td style="padding-right:16px;color:#888;">Email</td><td>{{ $contact['email'] }}</td></tr>
                                @if (! empty($contact['phone']))
                                    <tr><td style="padding-right:16px;color:#888;">Telepon</td><td>{{ $contact['phone'] }}</td></tr>
                                @endif
                            </table>
                            <div style="margin-top:20px;padding:16px;background:#f7f7f9;border-radius:4px;white-space:pre-line;line-height:1.6;">{{ $contact['message'] }}</div>
                            <p style="margin:20px 0 0;color:#888;font-size:12px;">
                                Balas email ini untuk menjawab langsung ke {{ $contact['email'] }}.
                                Dikirim {{ now()->format('d M Y H:i') }} dari {{ config('app.url') }}.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
