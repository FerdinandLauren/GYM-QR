<?php

namespace App\Console\Commands;

use App\Support\PhoneNumber;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

/**
 * Membuat akun admin pertama. Menampilkan password dan secret TOTP sekali.
 */
final class CreateAdmin extends Command
{
    protected $signature = 'app:create-admin {phone} {--name=Admin} {--password=}';

    protected $description = 'Buat akun admin dengan password dan TOTP';

    public function handle(Google2FA $google2fa): int
    {
        $phone = PhoneNumber::normalize((string) $this->argument('phone'));

        if (DB::table('users')->where('phone_number', $phone)->exists()) {
            $this->error('Nomor sudah terdaftar.');

            return self::FAILURE;
        }

        $password = (string) ($this->option('password') ?: Str::password(16));
        $secret = $google2fa->generateSecretKey();

        $userId = DB::table('users')->insertGetId([
            'full_name' => (string) $this->option('name'),
            'phone_number' => $phone,
            'role' => 'admin',
            'password_hash' => Hash::make($password),
            'totp_secret' => Crypt::encryptString($secret),
            'is_active' => 1,
        ]);

        $this->info("Admin dibuat: user_id={$userId}, phone={$phone}");
        $this->line("Password sementara: {$password}");
        $this->line("TOTP secret: {$secret}");
        $this->line('Simpan sekarang. Password dan secret tidak ditampilkan lagi.');
        $this->line('URI authenticator: '.$google2fa->getQRCodeUrl('GymPass QR', $phone, $secret));

        return self::SUCCESS;
    }
}
