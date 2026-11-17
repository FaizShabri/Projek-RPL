<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Dispute;
use App\Models\Order;
use App\Models\Review;
use App\Models\StaffToko;
use App\Models\Toko;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PurgeDemoAccounts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'accounts:purge-demo {--force : Jalankan tanpa konfirmasi interaktif (hanya bila di testing)} {--action= : Pilihan aksi (delete / disable)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Membersihkan atau menonaktifkan akun demo/seeder secara terstruktur dan aman';

    /**
     * Daftar email akun demo yang dikenal dari seeder awal.
     */
    public const DEMO_EMAILS = [
        'mitra.laundry@pusatlaundry.id',
        'rina.wijaya@example.com',
        'ahmad.fauzi@example.com',
        'siti.nurhaliza@example.com',
        'dimas.pratama@example.com',
        'staff.tebet@pusatlaundry.id',
        'kurir.express@pusatlaundry.id',
        'admin@pusatlaundry.id',
        'super@pusatlaundry.id',
    ];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('====================================================');
        $this->info('  Pusat Laundry - Pembersihan Akun Demo & Seeder');
        $this->info('====================================================');

        // 1. Validasi Keamanan Keras:
        // Command MENOLAK jalan bila tidak ada minimal satu platform_owner aktif non-demo dengan 2FA terkonfirmasi!
        $validPlatformOwner = User::role(UserRole::PLATFORM_OWNER->value)
            ->where('status', 'active')
            ->whereNotNull('two_factor_confirmed_at')
            ->whereNotIn('email', self::DEMO_EMAILS)
            ->first();

        if (!$validPlatformOwner) {
            $this->error('GAGAL: Operasi dibatalkan demi keamanan.');
            $this->error('Tidak ditemukan minimal SATU akun Platform Owner aktif non-demo dengan 2FA terkonfirmasi.');
            $this->error('Silakan buat akun Platform Owner asli terlebih dahulu via `php artisan owner:bootstrap <email>`.');
            return Command::FAILURE;
        }

        $this->info("Verifikasi Platform Owner Asli: {$validPlatformOwner->name} ({$validPlatformOwner->email}) [2FA: Aktif]");

        // 2. Ambil semua akun demo yang tercatat
        $demoUsers = User::whereIn('email', self::DEMO_EMAILS)->get();

        if ($demoUsers->isEmpty()) {
            $this->info('Tidak ditemukan akun demo di database. Sistem sudah bersih!');
            return Command::SUCCESS;
        }

        $demoUserIds = $demoUsers->pluck('id')->toArray();

        // 3. Hitung data terkait akun demo
        $tokoCount = Toko::whereIn('owner_id', $demoUserIds)->count();
        $orderCount = Order::whereIn('customer_id', $demoUserIds)->count();
        $reviewCount = Review::whereIn('customer_id', $demoUserIds)->count();
        $disputeCount = Dispute::whereIn('customer_id', $demoUserIds)->count();
        $notificationCount = DB::table('notifications')
            ->where('notifiable_type', User::class)
            ->whereIn('notifiable_id', $demoUserIds)
            ->count();
        $tokenCount = DB::table('personal_access_tokens')
            ->where('tokenable_type', User::class)
            ->whereIn('tokenable_id', $demoUserIds)
            ->count();

        $this->newLine();
        $this->warn('Daftar Akun Demo yang Terdeteksi:');
        $tableData = $demoUsers->map(function ($u) {
            return [
                'ID' => $u->id,
                'Nama' => $u->name,
                'Email' => $u->email,
                'Role' => implode(', ', $u->getRoleNames()->toArray()),
                'Status' => $u->status,
            ];
        });
        $this->table(['ID', 'Nama', 'Email', 'Role', 'Status'], $tableData);

        $this->newLine();
        $this->info('Ringkasan Data Terkait Akun Demo:');
        $this->line("- Toko Laundry Milik Mitra Demo : {$tokoCount}");
        $this->line("- Transaksi / Order Terkait     : {$orderCount}");
        $this->line("- Review / Ulasan Customer Demo : {$reviewCount}");
        $this->line("- Dispute Terkait Customer Demo : {$disputeCount}");
        $this->line("- Notifikasi Pengguna Demo      : {$notificationCount}");
        $this->line("- Sesi / Token Aktif Akun Demo  : {$tokenCount}");

        // 4. Pilihan Aksi
        $action = $this->option('action');
        if (!$action) {
            $choice = $this->choice(
                'Pilih tindakan yang ingin dilakukan terhadap akun demo:',
                [
                    'disable' => 'Pertahankan data transaksi & toko, tetapi NONAKTIFKAN semua akun demo dan cabut seluruh token.',
                    'delete'  => 'HAPUS TOTAL akun demo beserta seluruh data terkait (order, toko, review, dispute, token).',
                    'cancel'  => 'Batalkan proses.',
                ],
                'disable'
            );

            if ($choice === 'cancel' || $choice === 'Batalkan proses.') {
                $this->info('Operasi dibatalkan oleh pengguna.');
                return Command::SUCCESS;
            }

            $action = ($choice === 'delete' || str_contains($choice, 'HAPUS TOTAL')) ? 'delete' : 'disable';
        }

        // Konfirmasi interaktif jika bukan --force
        if (!$this->option('force')) {
            $confirm = $this->confirm("Apakah Anda yakin ingin melanjutkan tindakan [{$action}]?", false);
            if (!$confirm) {
                $this->info('Operasi dibatalkan.');
                return Command::SUCCESS;
            }
        }

        // 5. Eksekusi Tindakan
        DB::transaction(function () use ($action, $demoUsers, $demoUserIds, $validPlatformOwner) {
            // Cabut semua token sesi akun demo
            DB::table('personal_access_tokens')
                ->where('tokenable_type', User::class)
                ->whereIn('tokenable_id', $demoUserIds)
                ->delete();

            if ($action === 'delete') {
                // Hapus data berelasi
                DB::table('order_status_histories')
                    ->whereIn('order_id', Order::whereIn('customer_id', $demoUserIds)->pluck('id'))
                    ->delete();
                DB::table('kurir_location_logs')
                    ->whereIn('order_id', Order::whereIn('customer_id', $demoUserIds)->pluck('id'))
                    ->delete();
                Dispute::whereIn('customer_id', $demoUserIds)->delete();
                Review::whereIn('customer_id', $demoUserIds)->delete();
                Order::whereIn('customer_id', $demoUserIds)->delete();
                StaffToko::whereIn('user_id', $demoUserIds)->delete();
                Toko::whereIn('owner_id', $demoUserIds)->delete();
                DB::table('notifications')
                    ->where('notifiable_type', User::class)
                    ->whereIn('notifiable_id', $demoUserIds)
                    ->delete();

                foreach ($demoUsers as $user) {
                    $user->delete();
                }

                AuditLog::record(
                    actorId: $validPlatformOwner->id,
                    action: 'PURGE_DEMO_ACCOUNTS_DELETED',
                    targetType: User::class,
                    targetId: null,
                    beforeState: ['demo_user_ids' => $demoUserIds],
                    afterState: ['action' => 'deleted', 'count' => count($demoUserIds)]
                );
            } else {
                // Nonaktifkan akun demo
                User::whereIn('id', $demoUserIds)->update([
                    'status' => 'disabled',
                ]);

                AuditLog::record(
                    actorId: $validPlatformOwner->id,
                    action: 'PURGE_DEMO_ACCOUNTS_DISABLED',
                    targetType: User::class,
                    targetId: null,
                    beforeState: ['demo_user_ids' => $demoUserIds],
                    afterState: ['action' => 'disabled', 'count' => count($demoUserIds)]
                );
            }
        });

        $this->newLine();
        $this->info("SUKSES: Pembersihan akun demo selesai dengan metode [{$action}].");
        $this->info('Audit log telah tersimpan secara permanen.');

        return Command::SUCCESS;
    }
}
