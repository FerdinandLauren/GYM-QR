<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * PRD §6.1 tabel 0. Satu baris konfigurasi gym; dijaga chk_gym_single_row.
     */
    public function up(): void
    {
        DB::statement(<<<'SQL'
CREATE TABLE gym_settings (
    gym_id                  TINYINT UNSIGNED PRIMARY KEY DEFAULT 1,
    gym_name                VARCHAR(100) NOT NULL,
    timezone                VARCHAR(64)  NOT NULL DEFAULT 'Asia/Jakarta',
    latitude                DECIMAL(10,8) NOT NULL,
    longitude               DECIMAL(11,8) NOT NULL,
    geofence_radius_m       SMALLINT UNSIGNED NOT NULL DEFAULT 30,
    geofence_accuracy_max_m SMALLINT UNSIGNED NOT NULL DEFAULT 150,
    rescan_guard_seconds    SMALLINT UNSIGNED NOT NULL DEFAULT 300,
    closing_time            TIME NOT NULL DEFAULT '23:00:00',
    qr_payload              VARCHAR(128) NOT NULL,
    updated_at              TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                            ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT chk_gym_single_row CHECK (gym_id = 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('gym_settings');
    }
};
