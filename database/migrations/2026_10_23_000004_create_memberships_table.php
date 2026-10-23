<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * PRD §6.1 tabel 3. Riwayat dipertahankan: tanpa ON DELETE CASCADE.
     */
    public function up(): void
    {
        DB::statement(<<<'SQL'
CREATE TABLE memberships (
    membership_id     INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id           INT UNSIGNED NOT NULL,
    package_id        INT UNSIGNED NOT NULL,
    type              ENUM('monthly_unlimited','single_visit') NOT NULL,
    remaining_visits  SMALLINT UNSIGNED NULL,
    price_snapshot    DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    start_date        DATE NOT NULL,
    end_date          DATE NULL,
    status            ENUM('active','expired','suspended','cancelled')
                      NOT NULL DEFAULT 'active',
    created_by_staff_id INT UNSIGNED NULL,
    sold_by_staff_id    INT UNSIGNED NULL,
    created_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                                ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_mem_user (user_id, status, end_date),
    CONSTRAINT fk_mem_user       FOREIGN KEY (user_id)        REFERENCES users(user_id),
    CONSTRAINT fk_mem_package    FOREIGN KEY (package_id)     REFERENCES packages(package_id),
    CONSTRAINT fk_mem_created_by FOREIGN KEY (created_by_staff_id) REFERENCES users(user_id),
    CONSTRAINT fk_mem_sold_by    FOREIGN KEY (sold_by_staff_id)    REFERENCES users(user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('memberships');
    }
};
