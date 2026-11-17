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
        // 1. Tambahkan kolom pendukung autentikasi email & password di tabel users
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'last_login_at')) {
                $table->timestamp('last_login_at')->nullable()->after('status');
            }
            if (!Schema::hasColumn('users', 'two_factor_recovery_codes')) {
                $table->text('two_factor_recovery_codes')->nullable()->after('two_factor_confirmed_at');
            }
        });

        // Hapus google_id jika masih ada
        if (Schema::hasColumn('users', 'google_id')) {
            try {
                Schema::table('users', function (Blueprint $table) {
                    $table->dropColumn('google_id');
                });
            } catch (\Throwable $e) {
                // SQLite terkadang membatasi dropColumn langsung pada tabel ber-index
            }
        }

        // 2. Buat tabel staff_invitations untuk sistem undangan staf (admin_owner, admin_toko, kurir)
        Schema::create('staff_invitations', function (Blueprint $table) {
            $table->id();
            $table->string('email')->index();
            $table->string('role');
            $table->unsignedBigInteger('toko_id')->nullable()->index();
            $table->foreignId('invited_by')->constrained('users')->onDelete('cascade');
            $table->string('token_hash', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->foreign('toko_id')->references('id')->on('tokos')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('staff_invitations');

        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'last_login_at')) {
                $table->dropColumn('last_login_at');
            }
            if (Schema::hasColumn('users', 'two_factor_recovery_codes')) {
                $table->dropColumn('two_factor_recovery_codes');
            }
        });
    }
};
