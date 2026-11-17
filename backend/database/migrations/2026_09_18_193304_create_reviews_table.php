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
        // Menyimpan review customer untuk toko ataupun kurir.
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->onDelete('cascade');
            
            // Kita pisah menjadi dua kolom nullable untuk referential integrity
            // Di level aplikasi (Model), kita bisa set validation agar salah satu harus terisi
            $table->foreignId('toko_id')->nullable()->constrained('tokos')->onDelete('cascade');
            $table->foreignId('kurir_id')->nullable()->constrained('users')->onDelete('cascade');
            
            $table->integer('rating'); // 1 sampai 5
            $table->text('komentar')->nullable();
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
