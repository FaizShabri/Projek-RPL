<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Akun Anda Telah Disetujui</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; line-height: 1.6; color: #333333; margin: 0; padding: 0; background-color: #f4f6f8; }
        .wrapper { max-width: 600px; margin: 30px auto; background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        .header { background: #1A3A5C; color: #ffffff; padding: 30px 20px; text-align: center; }
        .header h1 { margin: 0; font-size: 24px; font-weight: 800; }
        .content { padding: 30px 25px; }
        .btn { display: inline-block; padding: 12px 28px; background-color: #1A3A5C; color: #ffffff !important; text-decoration: none; border-radius: 8px; font-weight: bold; margin: 20px 0; text-align: center; }
        .footer { padding: 20px; text-align: center; font-size: 12px; color: #888888; background-color: #fcfcfc; border-top: 1px solid #eeeeee; }
        .badge { display: inline-block; background-color: #d1fae5; color: #065f46; padding: 4px 12px; border-radius: 9999px; font-weight: 700; font-size: 12px; }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="header">
            <h1>🧺 Pusat Laundry</h1>
        </div>
        <div class="content">
            <p><span class="badge">Akun Aktif</span></p>
            <h2>Selamat, {{ $user->name }}!</h2>
            <p>Pendaftaran akun Anda di <strong>Pusat Laundry</strong> telah disetujui oleh administrator.</p>
            <p>Sekarang Anda sudah dapat masuk ke aplikasi dan menikmati seluruh layanan laundry on-demand kami, mulai dari penjemputan cucian, pelacakan kurir real-time, hingga monitoring status cucian.</p>
            
            <div style="text-align: center;">
                <a href="{{ $loginUrl }}" class="btn">Masuk ke Akun Sekarang</a>
            </div>

            <p style="font-size: 13px; color: #666666; margin-top: 25px;">
                Jika tombol di atas tidak dapat diklik, salin tautan berikut ke browser Anda:<br>
                <a href="{{ $loginUrl }}" style="color: #1A3A5C; word-break: break-all;">{{ $loginUrl }}</a>
            </p>
        </div>
        <div class="footer">
            &copy; {{ date('Y') }} Pusat Laundry Indonesia. Hak Cipta Dilindungi.
        </div>
    </div>
</body>
</html>
