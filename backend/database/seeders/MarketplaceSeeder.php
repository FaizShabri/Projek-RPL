<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Toko;
use App\Models\Tarif;
use App\Models\Order;
use App\Models\Review;
use App\Enums\UserRole;
use Illuminate\Support\Facades\Hash;

class MarketplaceSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Buat Akun Owner Toko
        $owner = User::firstOrCreate(
            ['email' => 'mitra.laundry@pusatlaundry.id'],
            [
                'name' => 'Budi Santoso (Mitra)',
                'password' => Hash::make('password123'),
                'email_verified_at' => now(),
                'status' => 'active',
                'avatar' => 'https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?w=120&auto=format&fit=crop&q=80',
            ]
        );
        if (!$owner->hasRole(UserRole::OWNER_TOKO->value)) {
            $owner->assignRole(UserRole::OWNER_TOKO->value);
        }

        // 2. Buat Akun Customers untuk Ulasan
        $customers = [
            [
                'name' => 'Rina Wijaya',
                'email' => 'rina.wijaya@example.com',
                'avatar' => 'https://images.unsplash.com/photo-1494790108377-be9c29b29330?w=120&auto=format&fit=crop&q=80',
            ],
            [
                'name' => 'Ahmad Fauzi',
                'email' => 'ahmad.fauzi@example.com',
                'avatar' => 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=120&auto=format&fit=crop&q=80',
            ],
            [
                'name' => 'Siti Nurhaliza',
                'email' => 'siti.nurhaliza@example.com',
                'avatar' => 'https://images.unsplash.com/photo-1438761681033-6461ffad8d80?w=120&auto=format&fit=crop&q=80',
            ],
            [
                'name' => 'Dimas Pratama',
                'email' => 'dimas.pratama@example.com',
                'avatar' => 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?w=120&auto=format&fit=crop&q=80',
            ],
        ];

        $customerModels = [];
        foreach ($customers as $c) {
            $user = User::firstOrCreate(
                ['email' => $c['email']],
                [
                    'name' => $c['name'],
                    'password' => Hash::make('password123'),
                    'email_verified_at' => now(),
                    'status' => 'active',
                    'avatar' => $c['avatar'],
                ]
            );
            if (!$user->hasRole(UserRole::CUSTOMER->value)) {
                $user->assignRole(UserRole::CUSTOMER->value);
            }
            $customerModels[] = $user;
        }

        // 3. Data Toko Laundry Approved dengan koordinat nyata (Area Jabodetabek)
        $tokoData = [
            [
                'nama' => 'Pusat Laundry Express Tebet',
                'alamat' => 'Jl. Tebet Barat Dalam Raya No. 42, Tebet, Jakarta Selatan',
                'latitude' => -6.236120,
                'longitude' => 106.852440,
                'status' => 'approved',
                'tarifs' => [
                    ['jenis_layanan' => 'Cuci Kering Setrika (Reguler 2 Hari)', 'harga' => 8000],
                    ['jenis_layanan' => 'Cuci Kering Lipat (1 Hari)', 'harga' => 6000],
                    ['jenis_layanan' => 'Express 6 Jam (Cuci + Setrika)', 'harga' => 15000],
                    ['jenis_layanan' => 'Cuci Bed Cover King Size', 'harga' => 35000],
                    ['jenis_layanan' => 'Cuci Sepatu Sneakers', 'harga' => 30000],
                ],
                'reviews' => [
                    ['rating' => 5, 'komentar' => 'Wangi banget pakaiannya, lipatan rapi dan kurirnya ramah tepat waktu!'],
                    ['rating' => 5, 'komentar' => 'Express 6 jam beneran kilat, baju buat meeting terselamatkan.'],
                    ['rating' => 4, 'komentar' => 'Pelayanan memuaskan, harga bersahabat untuk anak kost.'],
                ],
            ],
            [
                'nama' => 'Clean & Fresh Laundry Sudirman',
                'alamat' => 'Jl. Jenderal Sudirman Kav. 25, Setiabudi, Jakarta Selatan',
                'latitude' => -6.215500,
                'longitude' => 106.821000,
                'status' => 'approved',
                'tarifs' => [
                    ['jenis_layanan' => 'Dry Clean Jas / Blazer', 'harga' => 45000],
                    ['jenis_layanan' => 'Cuci Kemeja Kerja Setrika Uap', 'harga' => 12000],
                    ['jenis_layanan' => 'Cuci Kiloan Premium (Anti Bakteri)', 'harga' => 10000],
                    ['jenis_layanan' => 'Cuci Gaun / Kebaya Pesta', 'harga' => 65000],
                ],
                'reviews' => [
                    ['rating' => 5, 'komentar' => 'Jas kerja saya bersih tanpa noda dan wangi elegan, sangat profesional.'],
                    ['rating' => 4, 'komentar' => 'Bagus dan rapi, pengerjaan cepat dan update status jelas.'],
                ],
            ],
            [
                'nama' => 'Harum Mewangi Laundry Kemang',
                'alamat' => 'Jl. Kemang Raya No. 18B, Mampang Prapatan, Jakarta Selatan',
                'latitude' => -6.262500,
                'longitude' => 106.815200,
                'status' => 'approved',
                'tarifs' => [
                    ['jenis_layanan' => 'Cuci Kiloan Wangi Aroma Sakura', 'harga' => 9000],
                    ['jenis_layanan' => 'Setrika Uap Kiloan', 'harga' => 5000],
                    ['jenis_layanan' => 'Cuci Boneka Ukuran Besar', 'harga' => 25000],
                    ['jenis_layanan' => 'Cuci Karpet per Meter', 'harga' => 18000],
                ],
                'reviews' => [
                    ['rating' => 5, 'komentar' => 'Parfum sakuranya tahan lama sampai seminggu di lemari. Top markotop!'],
                    ['rating' => 5, 'komentar' => 'Langganan keluarga tiap minggu, ga pernah kecewa.'],
                    ['rating' => 4, 'komentar' => 'Kemasan plastik tebal dan rapi, terima kasih.'],
                ],
            ],
            [
                'nama' => 'Berkah Laundry Kiloan Bendungan Hilir',
                'alamat' => 'Jl. Bendungan Hilir No. 88, Tanah Abang, Jakarta Pusat',
                'latitude' => -6.213800,
                'longitude' => 106.812200,
                'status' => 'approved',
                'tarifs' => [
                    ['jenis_layanan' => 'Cuci Kering Setrika Standar', 'harga' => 7000],
                    ['jenis_layanan' => 'Cuci Saja (Tanpa Setrika)', 'harga' => 4500],
                    ['jenis_layanan' => 'Cuci Selimut Tebal', 'harga' => 20000],
                ],
                'reviews' => [
                    ['rating' => 4, 'komentar' => 'Harga murah meriah di area Benhil, hasil cucian bersih.'],
                    ['rating' => 3, 'komentar' => 'Waktu pengerjaan agak terlambat 2 jam saat weekend ramai, tapi cucian bagus.'],
                ],
            ],
            [
                'nama' => 'EcoWash Modern Laundry Kelapa Gading',
                'alamat' => 'Jl. Boulevard Raya Blok FW No. 12, Kelapa Gading, Jakarta Utara',
                'latitude' => -6.158200,
                'longitude' => 106.908000,
                'status' => 'approved',
                'tarifs' => [
                    ['jenis_layanan' => 'Eco Laundry Cuci Hypoallergenic', 'harga' => 11000],
                    ['jenis_layanan' => 'Cuci Sepatu Premium Leather', 'harga' => 50000],
                    ['jenis_layanan' => 'Express Same Day 4 Jam', 'harga' => 18000],
                    ['jenis_layanan' => 'Cuci Helm Full Face', 'harga' => 25000],
                ],
                'reviews' => [
                    ['rating' => 5, 'komentar' => 'Detergen ramah lingkungan tidak bikin kulit alergi gatal, sangat puas!'],
                    ['rating' => 5, 'komentar' => 'Hasil cuci sepatu kulit kinclong seperti baru.'],
                ],
            ],
            [
                'nama' => 'Klinik Cuci & Setrika Grogol',
                'alamat' => 'Jl. Kyai Tapa No. 101, Grogol Petamburan, Jakarta Barat',
                'latitude' => -6.166800,
                'longitude' => 106.789500,
                'status' => 'approved',
                'tarifs' => [
                    ['jenis_layanan' => 'Paket Mahasiswa Cuci Kering Setrika', 'harga' => 6500],
                    ['jenis_layanan' => 'Setrika Saja Kilat', 'harga' => 4000],
                    ['jenis_layanan' => 'Cuci Jaket Parasut / Winter', 'harga' => 25000],
                ],
                'reviews' => [
                    ['rating' => 4, 'komentar' => 'Paket mahasiswanya hemat di kantong, lokasi dekat kampus Trisakti/Untar.'],
                    ['rating' => 4, 'komentar' => 'Bagus dan wangi.'],
                ],
            ],
            [
                'nama' => 'Sinar Mas Laundry BSD City',
                'alamat' => 'Ruko Golden Madrid Blok B No. 5, BSD City, Tangerang Selatan',
                'latitude' => -6.302100,
                'longitude' => 106.685300,
                'status' => 'approved',
                'tarifs' => [
                    ['jenis_layanan' => 'Cuci Kering Setrika Premium BSD', 'harga' => 9500],
                    ['jenis_layanan' => 'Cuci Gorden & Vitrase', 'harga' => 22000],
                    ['jenis_layanan' => 'Cuci Stroller Bayi', 'harga' => 60000],
                ],
                'reviews' => [
                    ['rating' => 5, 'komentar' => 'Cuci stroller anak bersih higienis dan bau susunya hilang total!'],
                    ['rating' => 5, 'komentar' => 'Layanan antar jemput cepat, recommended.'],
                ],
            ],
        ];

        foreach ($tokoData as $data) {
            $tarifs = $data['tarifs'];
            $reviews = $data['reviews'];
            unset($data['tarifs'], $data['reviews']);

            $toko = Toko::updateOrCreate(
                ['nama' => $data['nama']],
                array_merge($data, ['owner_id' => $owner->id])
            );

            // Seed Tarifs
            foreach ($tarifs as $t) {
                Tarif::updateOrCreate(
                    [
                        'toko_id' => $toko->id,
                        'jenis_layanan' => $t['jenis_layanan'],
                    ],
                    [
                        'harga' => $t['harga'],
                    ]
                );
            }

            // Seed Dummy Orders & Reviews
            foreach ($reviews as $idx => $rev) {
                $customer = $customerModels[$idx % count($customerModels)];

                // Buat dummy completed order untuk referensi review
                $order = Order::firstOrCreate(
                    [
                        'customer_id' => $customer->id,
                        'toko_id' => $toko->id,
                        'status' => 'selesai',
                        'alamat_jemput' => $customer->name . ' Alamat',
                    ],
                    [
                        'tarif_id' => $toko->tarifs->first()?->id,
                        'berat' => 3.5,
                        'total_biaya' => 28000,
                        'metode_bayar' => 'CASH',
                    ]
                );

                Review::updateOrCreate(
                    [
                        'order_id' => $order->id,
                        'toko_id' => $toko->id,
                    ],
                    [
                        'rating' => $rev['rating'],
                        'komentar' => $rev['komentar'],
                    ]
                );
            }
        }
    }
}
