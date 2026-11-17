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
        Schema::create('tokos', function (Blueprint $table) {
            $table->id();
            // Referensi owner toko
            $table->foreignId('owner_id')->constrained('users')->onDelete('cascade');
            
            // Detail toko
            $table->string('nama');
            $table->text('alamat');
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->string('dokumen_legalitas')->nullable(); // Path ke dokumen
            
            // Status persetujuan dari platform owner
            $table->enum('status', ['submitted', 'approved', 'rejected', 'suspended'])->default('submitted');
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tokos');
    }
};
