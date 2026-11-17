<?php

namespace App\Console\Commands;

use App\Enums\OrderStatus;
use App\Models\KurirLocationLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PurgeKurirLocationLogs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'kurir:purge-location-logs';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Hapus log lokasi kurir yang sudah lama, pertahankan log terakhir per order yang selesai untuk audit trail.';

    /**
     * Execute the console command.
     *
     * Strategi purge:
     * 1. Untuk order yang selesai/dibatalkan: simpan hanya log TERAKHIR per order.
     * 2. Untuk order aktif: simpan semua log (tidak dihapus).
     * 3. Hapus semua log yang lebih lama dari 7 hari (kecuali yang dilindungi di atas).
     */
    public function handle(): int
    {
        $cutoffDate = now()->subDays(7);

        $completedStatuses = [
            OrderStatus::SELESAI->value,
            OrderStatus::DIBATALKAN->value,
        ];

        // Langkah 1: Untuk order selesai/dibatalkan, dapatkan ID log terakhir per order
        // yang harus dipertahankan sebagai audit trail.
        $protectedLogIds = KurirLocationLog::query()
            ->whereHas('order', function ($query) use ($completedStatuses) {
                $query->whereIn('status', $completedStatuses);
            })
            ->select(DB::raw('MAX(id) as id'))
            ->groupBy('order_id')
            ->pluck('id');

        // Langkah 2: Hapus log lama dari order selesai/dibatalkan (kecuali yang terakhir)
        $deletedFromCompleted = KurirLocationLog::query()
            ->whereHas('order', function ($query) use ($completedStatuses) {
                $query->whereIn('status', $completedStatuses);
            })
            ->whereNotIn('id', $protectedLogIds)
            ->delete();

        // Langkah 3: Hapus log yang lebih lama dari 7 hari untuk semua order
        // (yang tidak dilindungi dan bukan order aktif)
        $deletedOldLogs = KurirLocationLog::query()
            ->where('created_at', '<', $cutoffDate)
            ->whereNotIn('id', $protectedLogIds)
            ->delete();

        $totalDeleted = $deletedFromCompleted + $deletedOldLogs;

        $this->info("Berhasil menghapus {$totalDeleted} log lokasi kurir.");
        $this->info("  - Dari order selesai/dibatalkan (non-terakhir): {$deletedFromCompleted}");
        $this->info("  - Log lama (> 7 hari): {$deletedOldLogs}");

        return self::SUCCESS;
    }
}
