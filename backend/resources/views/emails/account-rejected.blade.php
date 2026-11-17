<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pemberitahuan Pendaftaran Akun</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; line-height: 1.6; color: #333333; margin: 0; padding: 0; background-color: #f4f6f8; }
        .wrapper { max-width: 600px; margin: 30px auto; background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        .header { background: #1A3A5C; color: #ffffff; padding: 30px 20px; text-align: center; }
        .header h1 { margin: 0; font-size: 24px; font-weight: 800; }
        .content { padding: 30px 25px; }
        .reason-box { background-color: #fef2f2; border: 1px solid #fee2e2; border-left: 4px solid #ef4444; padding: 15px; border-radius: 6px; margin: 20px 0; font-size: 14px; color: #991b1b; }
        .btn { display: inline-block; padding: 12px 28px; background-color: #1A3A5C; color: #ffffff !important; text-decoration: none; border-radius: 8px; font-weight: bold; margin: 20px 0; text-align: center; }
        .footer { padding: 20px; text-align: center; font-size: 12px; color: #888888; background-color: #fcfcfc; border-top: 1px solid #eeeeee; }
        .badge { display: inline-block; background-color: #fee2e2; color: #991b1b; padding: 4px 12px; border-radius: 9999px; font-weight: 700; font-size: 12px; }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="header">
            <h1>🧺 Pusat Laundry</h1>
        </div>
        <div class="content">
            <p><span class="badge">Pendaftaran Ditolak</span></p>
            <h2>Halo, {{ $user->name }}</h2>
            <p>Terima kasih atas minat Anda bergabung dengan <strong>Pusat Laundry</strong>. Setelah ditinjau oleh tim administrator, mohon maaf saat ini pendaftaran akun Anda belum dapat disetujui.</p>
            
            <div class="reason-box">
                <strong>Alasan Penolakan:</strong><br>
                {{ $reason ?: 'Data pendaftaran belum memenuhi kriteria kebijakan platform.' }}
            </div>

            <p>Anda diperkenankan mendaftar ulang menggunakan data yang valid dan sesuai dengan ketentuan layanan kami.</p>

            <div style="text-align: center;">
                <a href="{{ $registerUrl }}" class="btn">Daftar Ulang</a>
            </div>
        </div>
        <div class="footer">
            &copy; {{ date('Y') }} Pusat Laundry Indonesia. Hak Cipta Dilindungi.
        </div>
    </div>
</body>
</html>
