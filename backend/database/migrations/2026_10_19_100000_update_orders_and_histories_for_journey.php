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
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('tarif_id')->nullable()->after('toko_id')->constrained('tarifs')->nullOnDelete();
            $table->text('catatan')->nullable()->after('alamat_jemput');
        });

        Schema::table('order_status_histories', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('order_id')->constrained('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['tarif_id']);
            $table->dropColumn(['tarif_id', 'catatan']);
        });

        Schema::table('order_status_histories', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropColumn('user_id');
        });
    }
};
