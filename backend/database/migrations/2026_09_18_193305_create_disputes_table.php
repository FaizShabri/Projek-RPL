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
        // Menyimpan data komplain atau dispute dari customer atau toko
        Schema::create('disputes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->onDelete('cascade');
            $table->foreignId('dibuka_oleh')->constrained('users'); // Siapa yang komplain
            
            $table->enum('status', ['open', 'in_review', 'under_review', 'resolved', 'rejected'])->default('open');
            $table->text('resolusi')->nullable(); // Keputusan / tindak lanjut admin
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('disputes');
    }
};
