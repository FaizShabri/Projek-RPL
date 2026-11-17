<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CleanupOldNotifications extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notifications:cleanup {--days=90 : Umur minimal notifikasi yang sudah dibaca (dalam hari) yang akan dibersihkan}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Membersihkan notifikasi yang telah dibaca dan lebih tua dari kebijakan retensi (default 90 hari)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $days = (int) $this->option('days');
        if ($days <= 0) {
            $days = 90;
        }

        $cutoff = now()->subDays($days);

        $this->info("Menghapus notifikasi yang sudah dibaca sebelum {$cutoff->toDateTimeString()} ({$days} hari)...");

        $deletedCount = DB::table('notifications')
            ->whereNotNull('read_at')
            ->where('created_at', '<', $cutoff)
            ->delete();

        $this->info("Berhasil membersihkan {$deletedCount} notifikasi usang.");

        return Command::SUCCESS;
    }
}
