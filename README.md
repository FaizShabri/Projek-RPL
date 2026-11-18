# Pusat Laundry - Marketplace Laundry On-Demand

LINK VIDEO : https://youtu.be/qYz8hDnoNrE

Pusat Laundry adalah platform marketplace layanan laundry berbasis web yang menghubungkan pelanggan (*customer*), pemilik outlet (*owner toko*), staf operasional (*admin toko*), dan mitra pengantaran (*kurir*) dalam satu ekosistem terpadu dengan pengawasan operasional terpusat (*platform owner* & *admin owner*).

---

## 🚀 Fitur Utama

- **Multi-Role & Multi-Tenant**: Arsitektur otorisasi berbasis 6 tingkatan peran (*Platform Owner*, *Admin Owner*, *Owner Toko*, *Admin Toko*, *Kurir*, *Customer*) dengan kepatuhan prinsip *Least Privilege*.
- **Autentikasi Aman & 2FA**:
  - Login email & password dengan Bcrypt hashing dan rate-limiting proteksi anti brute-force.
  - Alur registrasi bertahap (`pending_email` → `pending_approval` → `active`).
  - Verifikasi email via link bertanda tangan kriptografis (*Signed URL*).
  - Wajib Two-Factor Authentication (TOTP 2FA) untuk level manajemen (*Platform Owner* & *Admin Owner*) dilengkapi *Recovery Codes*.
- **Manajemen Akun Terpusat**:
  - Panel persetujuan dan penolakan akun baru (single & massal dengan alasan penolakan wajib).
  - Sistem undangan staf (*Staff Invitations*) sekali pakai berbatas waktu.
  - Audit logging permanen (*immutable*) untuk seluruh mutasi data sensitif.
- **Siklus Pemesanan Terintegrasi (Order Journey)**:
  - Marketplace pencarian toko laundry terdekat dan pilihan jenis layanan (kiloan, satuan, express).
  - Penjemputan cucian oleh kurir dengan pencatatan status real-time.
  - Penimbangan berat cucian dan konfirmasi tarif oleh admin outlet.
  - Pelacakan status pengerjaan cucian (dicuci, dikeringkan, disetrika, siap antar).
  - Pengantaran cucian kembali ke pelanggan dengan konfirmasi selesai dan penilaian (*review & rating*).
- **Live Tracking & Maps**: Integrasi Google Maps API untuk geolokasi alamat penjemputan dan monitoring pergerakan kurir.
- **AI Customer Assistant**: Integrasi asisten percakapan cerdas untuk FAQ dan bantuan layanan pesanan.
- **Keuangan & Settlement**: Laporan omset platform, pengaturan potongan komisi fleksibel, dan pencatatan payout mitra laundry.

---

## 👥 Hak Akses & Peran Pengguna (Roles)

1. **👑 Platform Owner**: Super administrator pemilik sistem. Mengelola seluruh akun, persetujuan pendaftaran, kebijakan komisi, pembagian saldo toko (*settlement payout*), dan audit log lengkap.
2. **🛡️ Admin Owner**: Tim operasional platform. Mengelola moderasi & persetujuan outlet toko baru, penanganan dispute / komplain pesanan pelanggan, serta pengawasan aktivitas umum.
3. **🏪 Owner Toko**: Pemilik usaha outlet laundry. Mengelola profil toko, jam operasional, daftar tarif & jenis layanan, staf toko, dan laporan saldo penghasilan.
4. **👔 Admin Toko**: Staf pengerjaan laundry. Mengonfirmasi pesanan masuk, menginput timbangan cucian, dan memperbarui status pencucian.
5. **🏍️ Mitra Kurir**: Petugas penjemputan & pengantaran. Menerima tugas antar-jemput pesanan, memperbarui status pengantaran, dan mengirimkan koordinat lokasi.
6. **🧺 Customer**: Pelanggan umum. Memilih toko laundry, melakukan pesanan, melacak cucian, memantau kurir, dan memberikan ulasan setelah selesai.

---

## 🛠️ Tech Stack

### Backend
- **Framework**: [Laravel 11](https://laravel.com/) (PHP 8.2+)
- **Authentication**: Laravel Sanctum (Token-based API Auth)
- **Authorization**: Spatie Laravel Permission
- **Two-Factor Auth**: PragmaRX Google2FA
- **Database**: PostgreSQL (Production) / SQLite (Local & Testing)
- **Queue & Mail**: Laravel Database Queue Worker & Mailable
- **Testing**: PHPUnit / Feature Testing

### Frontend
- **Framework**: [React 19](https://react.dev/) + [Vite](https://vitejs.dev/)
- **Styling**: Tailwind CSS v4
- **Routing**: React Router DOM v7
- **HTTP Client**: Axios dengan bearer token & request interceptor
- **Icons & UI**: Lucide React & Tailwind Components

---

## 📦 Panduan Instalasi & Menjalankan Proyek

### Prasyarat Sistem
- **PHP** >= 8.2 (dengan ekstensi `pdo`, `mbstring`, `openssl`, `curl`)
- **Composer** >= 2.0
- **Node.js** >= 18.x dan **npm**
- **Git**

---

### 1. Setup Backend (Laravel)

1. Masuk ke direktori backend:
   ```bash
   cd backend
   ```

2. Pasang dependensi PHP via Composer:
   ```bash
   composer install
   ```

3. Salin berkas konfigurasi lingkungan:
   ```bash
   cp .env.example .env
   ```

4. Buat Application Key:
   ```bash
   php artisan key:generate
   ```

5. Jalankan migrasi dan seeder awal:
   ```bash
   php artisan migrate --seed
   ```

6. *(Opsional)* Buat akun Platform Owner pertama:
   ```bash
   php artisan owner:bootstrap admin@domain.com
   ```

7. Jalankan Queue Worker untuk pengiriman email & background job:
   ```bash
   php artisan queue:work
   ```

8. Jalankan development server backend:
   ```bash
   php artisan serve
   ```
   *Backend API aktif di `http://localhost:8000`.*

---

### 2. Setup Frontend (React + Vite)

1. Buka terminal baru dan masuk ke direktori frontend:
   ```bash
   cd frontend
   ```

2. Pasang dependensi JavaScript via npm:
   ```bash
   npm install
   ```

3. Salin berkas konfigurasi lingkungan frontend:
   ```bash
   cp .env.example .env
   ```
   *(Pastikan `VITE_API_BASE_URL` mengarah ke `http://localhost:8000/api`)*

4. Jalankan development server frontend:
   ```bash
   npm run dev
   ```
   *Aplikasi web aktif di `http://localhost:5173`.*

---

## 🧪 Menjalankan Automated Test

Untuk menjalankan seluruh unit dan feature test pada backend:
```bash
cd backend
php artisan test
```

---

## 📂 Struktur Direktori Proyek

```
Project RPL/
├── docs/                      # Dokumentasi teknis & spesifikasi sistem
│   ├── PRD_RINGKAS.md         # Ringkasan Product Requirement Document
│   └── CHECKLIST_GOLIVE.md    # Checklist kesiapan rilis produksi
├── backend/                   # Laravel 11 REST API Application
│   ├── app/                   # Controllers, Models, Mailables, Enums, Policies
│   ├── config/                # Konfigurasi aplikasi
│   ├── database/              # Migrasi, factories, seeders
│   ├── routes/api.php         # Rute API RESTful
│   ├── tests/                 # Feature & Unit automated tests
│   └── .env.example           # Template variabel lingkungan backend
├── frontend/                  # React Vite Single Page Application
│   ├── src/
│   │   ├── api/               # Client axios & API endpoints
│   │   ├── components/        # Komponen UI modular
│   │   ├── contexts/          # Auth & Notification React Contexts
│   │   ├── layouts/           # Dashboard layout per role
│   │   └── pages/             # Halaman antarmuka pengguna
│   └── .env.example           # Template variabel lingkungan frontend
├── .gitignore                 # Konfigurasi pengecualian Git root
└── README.md                  # Dokumentasi utama proyek
```

---

## 📄 Lisensi

Proyek ini dikembangkan untuk keperluan akademik Rekayasa Perangkat Lunak (RPL).
