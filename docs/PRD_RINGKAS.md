# Pusat Laundry - Dokumentasi Arsitektur & Spesifikasi Sistem

Pusat Laundry adalah platform Marketplace Laundry On-Demand multi-toko (multi-tenant) berbasis web dengan arsitektur RESTful API (Laravel 11) dan Single Page Application (React Vite).

---

## 1. Arsitektur Hak Akses (Role-Based Access Control)

Platform menerapkan 6 peran pengguna (*Role-Based Access Control* menggunakan Spatie Laravel-Permission) dengan prinsip **Least Privilege**:

| Role | Deskripsi & Cakupan Wewenang |
|------|------------------------------|
| **Platform Owner** | Super administrator pemilik sistem. Berwenang mengelola seluruh akun, keuangan platform, audit log, persetujuan pendaftaran akun baru, komisi platform, dan payout toko. |
| **Admin Owner** | Staf operasional platform. Mengelola moderasi toko baru, penanganan dispute / komplain pesanan, dan monitoring umum tanpa hak akses finansial inti. |
| **Owner Toko** | Mitra laundry (pemilik outlet). Mengelola profil outlet, jenis layanan & tarif cucian, operasional pesanan, staf toko, dan laporan saldo settlement. |
| **Admin Toko** | Staf operasional outlet laundry. Menangani penimbangan cucian, konfirmasi pesanan masuk, dan pembaruan status pengerjaan cucian. |
| **Mitra Kurir** | Kurir penjemputan dan pengantaran cucian. Menerima penugasan, pembaruan status antar-jemput, dan pelacakan GPS real-time. |
| **Customer** | Pengguna umum / pelanggan laundry. Mencari toko laundry terdekat, memesan layanan, melacak status pesanan, live tracking kurir, pembayaran, dan komplain (dispute). |

---

## 2. Alur Autentikasi & Keamanan (Zero Trust & 2FA)

1. **Email & Password Authentication**:
   - Password di-hash menggunakan standar bawaan Laravel (Bcrypt).
   - Minimal 8 karakter dengan kombinasi huruf dan angka.
   - Rate limiting per IP + Email untuk mencegah *brute-force* dan *credential stuffing*.
2. **Pendaftaran & Persetujuan Akun**:
   - Pendaftaran akun baru berstatus awal `pending_email`.
   - Setelah email diverifikasi melalui *Signed URL*, akun berpindah status ke antrean `pending_approval`.
   - Platform Owner meninjau dan menyetujui (`active`) atau menolak (`rejected` dengan alasan wajib).
   - Opsi mode persetujuan dapat dikonfigurasi melalui *Business Settings* (`wajib` atau `otomatis`).
3. **Autentikasi Dua Faktor (2FA - TOTP)**:
   - **WAJIB** bagi role tingkat tinggi (`platform_owner` dan `admin_owner`).
   - Berbasis Time-based One-Time Password (Google Authenticator / Aegis) dan dilengkapi *Recovery Codes*.
4. **Sistem Undangan Staf**:
   - Akun staf (`admin_owner`, `admin_toko`, `kurir`) dibuat melalui tautan undangan bertanda tangan digital berbatas waktu (*single-use token*).
5. **Audit Logging**:
   - Seluruh mutasi sensitif (persetujuan akun, perubahan role, penonaktifan, penghapusan *soft delete*, perubahan komisi) dicatat secara permanen di tabel `audit_logs`.

---

## 3. Siklus Pesanan (Order Journey)

```
[Customer Checkout] 
        ↓
[Menunggu Konfirmasi Toko] 
        ↓
[Penugasan Kurir Jemput] 
        ↓
[Proses Penjemputan Cucian] 
        ↓
[Cucian Tiba di Toko & Ditimbang] 
        ↓
[Proses Cuci / Kering / Setrika] 
        ↓
[Siap Dikirim / Penugasan Kurir Antar] 
        ↓
[Pengantaran ke Alamat Customer] 
        ↓
[Pesanan Selesai / Konfirmasi Terima] 
        ↓
[Pemberian Ulasan & Rating]
```
