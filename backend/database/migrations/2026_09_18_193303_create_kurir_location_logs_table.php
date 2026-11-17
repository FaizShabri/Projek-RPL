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
        // Menyimpan log posisi kurir. Tabel ini akan tinggi frekuensi insert-nya.
        // Sebaiknya di-purge/archive secara periodik.
        Schema::create('kurir_location_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kurir_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('order_id')->nullable()->constrained('orders')->onDelete('cascade');
            $table->decimal('latitude', 10, 8);
            $table->decimal('longitude', 11, 8);
            $table->timestamp('created_at')->useCurrent(); // Hanya perlu waktu insert
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kurir_location_logs');
    }
};
