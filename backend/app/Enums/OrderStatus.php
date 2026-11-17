<?php

namespace App\Enums;

enum OrderStatus: string
{
    case MENUNGGU_KONFIRMASI = 'menunggu_konfirmasi';
    case MENUNGGU_KURIR = 'menunggu_kurir';
    case DIJEMPUT = 'dijemput';
    case DI_TOKO = 'di_toko';
    case MENUNGGU_PEMBAYARAN_INFO = 'menunggu_pembayaran_info';
    case DIPROSES = 'diproses';
    case SIAP_DIANTAR = 'siap_diantar';
    case DIANTAR = 'diantar';
    case COD_SELESAI = 'cod_selesai';
    case SELESAI = 'selesai';
    case DIBATALKAN = 'dibatalkan';

    /**
     * Label representasi manusia dalam Bahasa Indonesia.
     */
    public function label(): string
    {
        return match ($this) {
            self::MENUNGGU_KONFIRMASI => 'Menunggu Konfirmasi Toko',
            self::MENUNGGU_KURIR => 'Menunggu Kurir Mengambil',
            self::DIJEMPUT => 'Pakaian Dijemput Kurir',
            self::DI_TOKO => 'Tiba di Toko Laundry',
            self::MENUNGGU_PEMBAYARAN_INFO => 'Menunggu Info Biaya COD',
            self::DIPROSES => 'Sedang Dicuci & Disetrika',
            self::SIAP_DIANTAR => 'Pakaian Bersih Siap Diantar',
            self::DIANTAR => 'Kurir Sedang Mengantar',
            self::COD_SELESAI => 'COD Selesai & Diterima',
            self::SELESAI => 'Pesanan Selesai',
            self::DIBATALKAN => 'Pesanan Dibatalkan',
        };
    }

    /**
     * Apakah order masih dalam window pembatalan oleh customer?
     */
    public function canCancelByCustomer(): bool
    {
        return in_array($this, [
            self::MENUNGGU_KONFIRMASI,
            self::MENUNGGU_KURIR,
        ]);
    }
}
