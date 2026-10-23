<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Login member pindah ke OTP email. Nomor HP tetap wajib dan unik untuk
 * pencarian kasir serta kontak (keputusan owner, menggantikan D10 WhatsApp).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('email', 255)->nullable()->unique()->after('phone_number');
        });

        Schema::table('otp_requests', function (Blueprint $table): void {
            $table->string('email', 255)->nullable()->after('phone_number');
            $table->string('phone_number', 20)->nullable()->change();
            $table->index('email', 'idx_otp_email');
        });
    }

    public function down(): void
    {
        Schema::table('otp_requests', function (Blueprint $table): void {
            $table->dropIndex('idx_otp_email');
            $table->dropColumn('email');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique(['email']);
            $table->dropColumn('email');
        });
    }
};
