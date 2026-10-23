<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * PRD §6.1 tabel 5. Satu baris = satu sesi kunjungan; chk_logs_geo
     * mengizinkan check-in manual tanpa koordinat, menolak scan QR tanpa koordinat.
     */
    public function up(): void
    {
        DB::statement(<<<'SQL'
CREATE TABLE attendance_logs (
    log_id             BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id            INT UNSIGNED NOT NULL,
    membership_id      INT UNSIGNED NOT NULL,
    locker_id          INT UNSIGNED NULL,
    locker_state       ENUM('pending','none','assigned','returned') NOT NULL DEFAULT 'pending',
    assigned_by_staff_id INT UNSIGNED NULL,
    assigned_at        DATETIME NULL,
    identity_verified_by INT UNSIGNED NULL,
    identity_verified_at DATETIME NULL,
    checkin_time       DATETIME NOT NULL,
    locker_returned_time DATETIME NULL,
    checkout_time      DATETIME NULL,
    checkout_type      ENUM('locker_returned','staff_checkout','auto_close') NULL,
    checkin_method     ENUM('qr_scan','manual_receptionist') NOT NULL DEFAULT 'qr_scan',
    manual_reason      ENUM('no_phone','battery_dead','camera_broken','gps_issue','other') NULL,
    latitude           DECIMAL(10,8) NULL,
    longitude          DECIMAL(11,8) NULL,
    gps_accuracy_m     DECIMAL(6,2)  NULL,
    idempotency_key    VARCHAR(64)   NULL,
    created_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_logs_idempotency (user_id, idempotency_key),
    KEY idx_logs_user_time (user_id, checkin_time),
    KEY idx_logs_checkin (checkin_time),
    KEY idx_logs_open (checkout_time),
    KEY idx_logs_locker (locker_id),
    CONSTRAINT fk_logs_user      FOREIGN KEY (user_id)      REFERENCES users(user_id),
    CONSTRAINT fk_logs_member    FOREIGN KEY (membership_id) REFERENCES memberships(membership_id),
    CONSTRAINT fk_logs_locker    FOREIGN KEY (locker_id)    REFERENCES lockers(locker_id),
    CONSTRAINT fk_logs_staff     FOREIGN KEY (assigned_by_staff_id) REFERENCES users(user_id),
    CONSTRAINT fk_logs_verifier  FOREIGN KEY (identity_verified_by) REFERENCES users(user_id),
    CONSTRAINT chk_logs_geo CHECK (
        (checkin_method = 'manual_receptionist')
        OR (latitude IS NOT NULL AND longitude IS NOT NULL)
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_logs');
    }
};
