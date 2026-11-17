<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class BootstrapPlatformOwner extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'owner:bootstrap {email : Alamat email akun Platform Owner} {--name=Platform Super Owner : Nama lengkap Platform Owner}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Membuat atau menetapkan akun Platform Owner pertama secara interaktif dan aman (Zero Trust)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $email = trim($this->argument('email'));
        $name = trim($this->option('name'));

        // Validasi format email
        $validator = Validator::make(['email' => $email], [
            'email' => 'required|email|max:255',
        ]);

        if ($validator->fails()) {
            $this->error('Alamat email tidak valid: ' . $validator->errors()->first('email'));
            return Command::FAILURE;
        }

        $this->info("Menyiapkan akun Platform Owner untuk: {$email}");

        // Input password interaktif (disembunyikan)
        $password = $this->secret('Masukkan password Platform Owner (minimal 8 karakter):');
        if (strlen((string) $password) < 8) {
            $this->error('Password terlalu pendek! Minimal 8 karakter.');
            return Command::FAILURE;
        }

        $passwordConfirm = $this->secret('Konfirmasi ulang password:');
        if ($password !== $passwordConfirm) {
            $this->error('Konfirmasi password tidak cocok!');
            return Command::FAILURE;
        }

        // Cari atau buat User
        $user = User::firstOrNew(['email' => $email]);
        $isNew = !$user->exists;

        $user->name = $user->name ?: $name;
        $user->password = Hash::make($password);
        $user->status = 'active';
        $user->email_verified_at = now();
        // Wajib setup 2FA saat login pertama: reset status 2FA
        $user->two_factor_secret = null;
        $user->two_factor_confirmed_at = null;
        $user->two_factor_recovery_codes = null;
        $user->save();

        // Assign role platform_owner
        if (!$user->hasRole(UserRole::PLATFORM_OWNER->value)) {
            $user->assignRole(UserRole::PLATFORM_OWNER->value);
        }

        // Buat user profile default jika belum ada
        if (!$user->profile) {
            UserProfile::create([
                'user_id' => $user->id,
                'nama_lengkap' => $user->name,
                'alamat_lengkap' => 'Kantor Pusat Platform Laundry, Jakarta',
                'rt_rw' => '001/001',
                'nomor_telepon' => '081100000000',
            ]);
        }

        // Catat ke Audit Log sistem
        AuditLog::record(
            actorId: $user->id,
            action: 'BOOTSTRAP_PLATFORM_OWNER',
            targetType: User::class,
            targetId: $user->id,
            beforeState: null,
            afterState: [
                'email' => $user->email,
                'name' => $user->name,
                'role' => UserRole::PLATFORM_OWNER->value,
                'is_new_user' => $isNew,
                'email_verified' => true,
                'requires_2fa_setup' => true,
                'bootstrapped_at' => now()->toIso8601String(),
            ]
        );

        $this->info("✅ Berhasil! Akun Platform Owner ({$email}) telah aktif.");
        $this->warn("⚠️  PERHATIAN: Saat login pertama kali, akun ini DIWAJIBKAN untuk men-setup 2FA (Authenticator TOTP).");

        return Command::SUCCESS;
    }
}
