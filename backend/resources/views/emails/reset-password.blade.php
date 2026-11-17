<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Permintaan Reset Password - Pusat Laundry</title>
</head>
<body style="font-family: Arial, sans-serif; background-color: #f4f6f8; margin: 0; padding: 24px; color: #333;">
    <table width="100%" border="0" cellspacing="0" cellpadding="0">
        <tr>
            <td align="center">
                <table width="600" style="background-color: #ffffff; border-radius: 12px; padding: 32px; box-shadow: 0 4px 6px rgba(0,0,0,0.05);">
                    <tr>
                        <td align="center" style="padding-bottom: 24px;">
                            <h1 style="color: #1A3A5C; margin: 0; font-size: 24px;">🧺 Pusat Laundry</h1>
                            <p style="color: #666; font-size: 13px; margin-top: 4px;">Keamanan Akun & Transaksi</p>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <p style="font-size: 15px;">Halo <strong>{{ $user->name }}</strong>,</p>
                            <p style="font-size: 14px; line-height: 1.6; color: #555;">
                                Kami menerima permintaan untuk mereset password akun Pusat Laundry Anda. Klik tombol di bawah ini untuk membuat password baru yang aman:
                            </p>
                            <div style="text-align: center; margin: 32px 0;">
                                <a href="{{ $resetUrl }}" style="background-color: #D9534F; color: #ffffff; text-decoration: none; padding: 12px 28px; border-radius: 8px; font-weight: bold; font-size: 14px; display: inline-block;">
                                    Atur Ulang Password
                                </a>
                            </div>
                            <p style="font-size: 12px; color: #888; line-height: 1.5;">
                                Tautan reset password ini hanya berlaku satu kali dan akan kedaluwarsa dalam 60 menit.<br>
                                Demi keamanan, setelah password diatur ulang, seluruh sesi login dan token aktif akun Anda akan dicabut secara otomatis.
                            </p>
                            <hr style="border: none; border-top: 1px solid #eee; margin: 24px 0;">
                            <p style="font-size: 11px; color: #aaa; text-align: center;">
                                Jika Anda tidak meminta reset password, akun Anda tetap aman dan Anda dapat mengabaikan email ini.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
