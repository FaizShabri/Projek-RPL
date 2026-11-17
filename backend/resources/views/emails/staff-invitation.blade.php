<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Undangan Bergabung Staf - Pusat Laundry</title>
</head>
<body style="font-family: Arial, sans-serif; background-color: #f4f6f8; margin: 0; padding: 24px; color: #333;">
    <table width="100%" border="0" cellspacing="0" cellpadding="0">
        <tr>
            <td align="center">
                <table width="600" style="background-color: #ffffff; border-radius: 12px; padding: 32px; box-shadow: 0 4px 6px rgba(0,0,0,0.05);">
                    <tr>
                        <td align="center" style="padding-bottom: 24px;">
                            <h1 style="color: #1A3A5C; margin: 0; font-size: 24px;">🧺 Pusat Laundry</h1>
                            <p style="color: #666; font-size: 13px; margin-top: 4px;">Undangan Resmi Bergabung sebagai Staf</p>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <p style="font-size: 15px;">Halo Rekan,</p>
                            <p style="font-size: 14px; line-height: 1.6; color: #555;">
                                Anda telah diundang oleh <strong>{{ $inviter->name }}</strong> untuk bergabung ke platform Pusat Laundry sebagai:
                            </p>
                            <div style="background-color: #f8fafc; border-left: 4px solid #1A3A5C; padding: 12px 16px; margin: 16px 0; border-radius: 4px;">
                                <p style="margin: 0; font-size: 14px;"><strong>Peran:</strong> {{ $roleLabel }}</p>
                                @if($toko)
                                <p style="margin: 4px 0 0 0; font-size: 14px;"><strong>Toko:</strong> {{ $toko->nama }}</p>
                                @endif
                            </div>
                            <p style="font-size: 14px; line-height: 1.6; color: #555;">
                                Untuk menerima undangan dan membuat password akun Anda sendiri secara aman, silakan klik tombol di bawah ini:
                            </p>
                            <div style="text-align: center; margin: 32px 0;">
                                <a href="{{ $inviteUrl }}" style="background-color: #2ECC71; color: #ffffff; text-decoration: none; padding: 12px 28px; border-radius: 8px; font-weight: bold; font-size: 14px; display: inline-block;">
                                    Terima Undangan & Buat Akun
                                </a>
                            </div>
                            <p style="font-size: 12px; color: #888; line-height: 1.5;">
                                Tautan undangan ini hanya berlaku satu kali dan akan kedaluwarsa pada <strong>{{ $expiresAt }}</strong>.<br>
                                Administrator tidak pernah mengetahui atau menentukan password Anda. Anda akan menentukan password Anda sendiri secara mandiri.
                            </p>
                            <hr style="border: none; border-top: 1px solid #eee; margin: 24px 0;">
                            <p style="font-size: 11px; color: #aaa; text-align: center;">
                                Pusat Laundry menjunjung tinggi prinsip keamanan Zero Trust dan perlindungan privasi data.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
