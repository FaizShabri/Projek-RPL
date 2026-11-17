# Checklist Kesiapan Produksi (Go-Live) - Pusat Laundry

Dokumen ini menjadi panduan verifikasi keamanan sebelum aplikasi di-deploy ke lingkungan produksi.

---

## 1. Autentikasi & Akun
- [x] **Pembersihan Akun Demo**: Tidak ada akun demo atau kredensial default yang tertanam di kode sumber produksi.
- [x] **Jalur Cepat (Quick Login) Nonaktif**: Endpoint pengujian dinonaktifkan / dihapus dari rute publik.
- [x] **Bootstrap Platform Owner**: Platform Owner pertama dibentuk secara interaktif melalui perintah aman:
  ```bash
  php artisan owner:bootstrap <email@domain.com>
  ```
- [x] **2FA Wajib**: Akun Platform Owner dan Admin Owner wajib menyelesaikan setup 2FA (TOTP) saat login pertama.
- [x] **Verifikasi Email**: Fitur verifikasi email aktif dengan queue worker.
- [x] **Persetujuan Pendaftaran Akun**: Mode persetujuan pendaftaran akun baru berjalan sesuai kebijakan (`wajib` disetujui atau `otomatis`).

---

## 2. Keamanan & Konfigurasi Lingkungan
- [x] **File Kredensial (.env)**: File `.env` tidak pernah diikutsertakan ke dalam repository Git.
- [x] **Kunci Aplikasi (APP_KEY)**: Dihasilkan secara independen di server produksi (`php artisan key:generate`).
- [x] **Mode Debug**: `APP_DEBUG=false` pada lingkungan produksi.
- [x] **Penyedia Layanan Pihak Ketiga**:
  - Google Maps API Key dibatasi per-domain referer (HTTP Referer restriction).
  - Kunci API AI / Chatbot (Anthropic) disimpan melalui variabel lingkungan server saja.

---

## 3. Database & Worker
- [x] Migrasi database telah selesai dijalankan:
  ```bash
  php artisan migrate --force
  ```
- [x] Seeder peran dan izin telah dieksekusi:
  ```bash
  php artisan db:seed --class=RoleAndPermissionSeeder --force
  ```
- [x] Antrean pengerjaan email dan background tasks (Queue Worker) berjalan sebagai daemon (Supervisor / Systemd):
  ```bash
  php artisan queue:work --tries=3
  ```
