<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * PRD §6.1 tabel 2. Sumber tipe membership (FR-ADM-01).
     */
    public function up(): void
    {
        DB::statement(<<<'SQL'
CREATE TABLE packages (
    package_id    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code          VARCHAR(30)  NOT NULL,
    name          VARCHAR(100) NOT NULL,
    type          ENUM('monthly_unlimited','single_visit') NOT NULL,
    duration_days SMALLINT UNSIGNED NOT NULL,
    visit_quota   SMALLINT UNSIGNED NULL,
    price         DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    is_active     TINYINT(1)    NOT NULL DEFAULT 1,
    created_at    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP
                                ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_packages_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('packages');
    }
};
