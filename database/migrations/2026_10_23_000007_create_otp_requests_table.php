<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * PRD §6.1 tabel 6. OTP disimpan sebagai hash, bukan plaintext.
     */
    public function up(): void
    {
        DB::statement(<<<'SQL'
CREATE TABLE otp_requests (
    otp_id       BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    phone_number VARCHAR(20) NOT NULL,
    code_hash    VARCHAR(255) NOT NULL,
    attempts     TINYINT UNSIGNED NOT NULL DEFAULT 0,
    expires_at   DATETIME NOT NULL,
    consumed_at  DATETIME NULL,
    request_ip   VARBINARY(16) NULL,
    created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_otp_phone (phone_number, created_at),
    KEY idx_otp_cleanup (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('otp_requests');
    }
};
