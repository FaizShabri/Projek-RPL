# Checklist Go-Live Pusat Laundry

Pastikan seluruh item dalam daftar periksa ini terverifikasi sebelum sistem dipublikasikan ke production:

## 1. Keamanan Autentikasi & Akun
- [x] Fitur akun demo dan mode pengembang dihapus total dari frontend dan backend.
- [x] Tidak ada rute login cepat tanpa password (`POST /api/auth/dev-login` telah dieliminasi).
- [x] Password akun demo bawaan seeder telah dinonaktifkan/dibersihkan melalui `php artisan accounts:purge-demo`.
- [x] Minimal satu akun Platform Owner asli telah di-bootstrap menggunakan `php artisan owner:bootstrap <email>`.
- [x] Verifikasi 2FA (TOTP) wajib aktif dan terkonfirmasi untuk akun `platform_owner` dan `admin_owner`.
- [x] Rate limiting login (5 percobaan per menit dengan lockout IP + email) aktif.
- [x] Respons kesalahan login selalu generik untuk mencegah kebocoran user enumeration.

## 2. Alur Registrasi & Approval Akun
- [x] Setiap registrasi mandiri customer wajib melalui verifikasi email bertanda tangan URL (signed route).
- [x] Pengaturan persetujuan pendaftaran akun baru berjalan sesuai kebijakan bisnis (`approval_mode: manual / auto`).
- [x] Akun yang belum berstatus `active` diblokir dari login dan tidak dapat mengakses resource platform.
- [x] Notifikasi in-app dan email tersampaikan otomatis ke admin dan calon pengguna.
- [x] Anti-spam registrasi: rate limit IP, pembatasan kuota per IP, dan penolakan domain disposable email aktif.

## 3. Manajemen Akun & Hak Akses (Least Privilege)
- [x] Pemisahan hak akses ditegakkan di 2 lapis (Route Middleware + Controller Policy) dengan permission `accounts.approve` dan `accounts.manage`.
- [x] Role `platform_owner` tidak dapat dihapus, dinonaktifkan, atau diturunkan oleh siapapun lewat UI.
- [x] Penonaktifan akun langsung mencabut seluruh sesi token Sanctum aktif (*immediate lockout*).
- [x] Seluruh mutasi akun (approval, tolak, ubah role, toggle status, revoke session, reset password) dicatat dalam `audit_logs` *immutable*.
- [x] Tidak ada password maupun token akses yang bocor pada log aplikasi, audit log, maupun respons API.

## 4. Infrastruktur & Layanan Eksternal
- [x] Konfigurasi environment `.env` production menggunakan driver database dan cache yang sesuai.
- [x] Antrean email worker (`php artisan queue:work`) berjalan sebagai daemon/service latar belakang.
- [x] Scheduled cron (`php artisan schedule:run`) berjalan untuk retensi notifikasi dan pembersihan berkala.
