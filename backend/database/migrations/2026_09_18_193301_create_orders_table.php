<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Tabel transaksi utama
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('users');
            $table->foreignId('toko_id')->constrained('tokos');
            $table->foreignId('kurir_id')->nullable()->constrained('users'); // Nullable di awal sebelum kurir ambil
            
            $table->enum('status', [
                'pending',          // Menunggu konfirmasi / kurir
                'pickup_process',   // Kurir menuju lokasi customer
                'in_laundry',       // Sedang diproses di toko
                'delivery_process', // Kurir mengantar kembali ke customer
                'completed',        // Selesai
                'cancelled'         // Dibatalkan
            ])->default('pending');
            
            $table->text('alamat_jemput');
            $table->decimal('berat', 8, 2)->nullable(); // Bisa diinput toko setelah di timbang
            $table->decimal('total_biaya', 12, 2)->nullable();
            
            // Sesuai requirement, hanya mengakomodasi cash
            $table->string('metode_bayar')->default('CASH'); 
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
