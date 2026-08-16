<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Kode OTP</title>
</head>
<body style="margin:0; padding:0; background:#f4f5f7; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f5f7; padding: 32px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="480" cellpadding="0" cellspacing="0" style="background:#ffffff; border-radius:12px; overflow:hidden;">
                    <tr>
                        <td style="background:#4f46e5; padding:24px 32px;">
                            <span style="color:#ffffff; font-size:18px; font-weight:700;">SMK Adaptif</span>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:32px;">
                            <p style="font-size:15px; color:#1f2430; margin:0 0 8px;">Halo {{ $name }},</p>
                            <p style="font-size:14px; color:#4b5563; margin:0 0 24px; line-height:1.5;">
                                @if($isReset)
                                    Kami menerima permintaan untuk mereset password akun Anda. Masukkan kode berikut di aplikasi untuk melanjutkan:
                                @else
                                    Terima kasih sudah mendaftar. Masukkan kode berikut di aplikasi untuk memverifikasi akun Anda:
                                @endif
                            </p>
                            <div style="background:#eef2ff; border-radius:10px; padding:20px; text-align:center; margin-bottom:24px;">
                                <span style="font-size:32px; font-weight:700; letter-spacing:8px; color:#4338ca;">{{ $code }}</span>
                            </div>
                            <p style="font-size:13px; color:#6b7280; margin:0 0 4px; line-height:1.5;">
                                Kode ini berlaku selama 15 menit. Jangan bagikan kode ini ke siapapun.
                            </p>
                            <p style="font-size:13px; color:#6b7280; margin:0; line-height:1.5;">
                                Kalau Anda tidak merasa melakukan permintaan ini, abaikan saja email ini.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:16px 32px; background:#f9fafb; text-align:center;">
                            <span style="font-size:11px; color:#9ca3af;">Email otomatis dari SMK Adaptif — mohon tidak membalas email ini.</span>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
