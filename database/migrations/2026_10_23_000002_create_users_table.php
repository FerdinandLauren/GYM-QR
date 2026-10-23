<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * PRD §6.1 tabel 1. Soft delete; nomor HP tetap unik termasuk user terhapus.
     */
    public function up(): void
    {
        DB::statement(<<<'SQL'
CREATE TABLE users (
    user_id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    full_name       VARCHAR(100) NOT NULL,
    phone_number    VARCHAR(20)  NOT NULL,
    photo_url       VARCHAR(255) NULL,
    role            ENUM('member','receptionist','admin') NOT NULL DEFAULT 'member',
    password_hash   VARCHAR(255) NULL,
    totp_secret     VARCHAR(255) NULL,
    is_active       TINYINT(1)   NOT NULL DEFAULT 1,
    consent_at      DATETIME     NULL,
    consent_version VARCHAR(20)  NULL,
    created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
                                 ON UPDATE CURRENT_TIMESTAMP,
    deleted_at      TIMESTAMP    NULL DEFAULT NULL,
    UNIQUE KEY uq_users_phone (phone_number),
    KEY idx_users_role (role, is_active),
    KEY idx_users_deleted (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
