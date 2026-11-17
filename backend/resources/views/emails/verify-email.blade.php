<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Verifikasi Alamat Email Anda - Pusat Laundry</title>
</head>
<body style="font-family: Arial, sans-serif; background-color: #f4f6f8; margin: 0; padding: 24px; color: #333;">
    <table width="100%" border="0" cellspacing="0" cellpadding="0">
        <tr>
            <td align="center">
                <table width="600" style="background-color: #ffffff; border-radius: 12px; padding: 32px; box-shadow: 0 4px 6px rgba(0,0,0,0.05);">
                    <tr>
                        <td align="center" style="padding-bottom: 24px;">
                            <h1 style="color: #1A3A5C; margin: 0; font-size: 24px;">🧺 Pusat Laundry</h1>
                            <p style="color: #666; font-size: 13px; margin-top: 4px;">Platform Marketplace Laundry On-Demand</p>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <p style="font-size: 15px;">Halo <strong>{{ $user->name }}</strong>,</p>
                            <p style="font-size: 14px; line-height: 1.6; color: #555;">
                                Terima kasih telah mendaftar di Pusat Laundry! Silakan klik tombol di bawah ini untuk memverifikasi alamat email Anda agar dapat mulai membuat pesanan dan mengakses seluruh layanan kami.
                            </p>
                            <div style="text-align: center; margin: 32px 0;">
                                <a href="{{ $verificationUrl }}" style="background-color: #1A3A5C; color: #ffffff; text-decoration: none; padding: 12px 28px; border-radius: 8px; font-weight: bold; font-size: 14px; display: inline-block;">
                                    Verifikasi Email Saya
                                </a>
                            </div>
                            <p style="font-size: 12px; color: #888; line-height: 1.5;">
                                Tautan verifikasi ini aman dan akan kedaluwarsa dalam 60 menit.<br>
                                Jika tombol di atas tidak dapat diklik, salin dan tempel tautan berikut ke browser Anda:<br>
                                <a href="{{ $verificationUrl }}" style="color: #4A90D9; word-break: break-all;">{{ $verificationUrl }}</a>
                            </p>
                            <hr style="border: none; border-top: 1px solid #eee; margin: 24px 0;">
                            <p style="font-size: 11px; color: #aaa; text-align: center;">
                                Jika Anda tidak merasa mendaftar di Pusat Laundry, abaikan email ini.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
