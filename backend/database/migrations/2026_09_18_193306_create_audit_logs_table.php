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
        // Menyimpan log semua aksi kritikal (Immutable log)
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            // Actor yang melakukan aksi (null jika oleh sistem otomatis)
            $table->foreignId('actor_id')->nullable()->constrained('users')->onDelete('set null');
            
            $table->string('action'); // misal: "APPROVE_TOKO", "UPDATE_TARIF"
            
            // Polymorphic relation ke entitas apa saja (Order, Toko, Tarif, dsb)
            $table->string('target_type')->nullable();
            $table->unsignedBigInteger('target_id')->nullable();
            
            // Payload data untuk trace perubahan
            $table->json('before_state')->nullable();
            $table->json('after_state')->nullable();
            
            // HANYA created_at. Tidak ada updated_at karena ini data immutable.
            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
