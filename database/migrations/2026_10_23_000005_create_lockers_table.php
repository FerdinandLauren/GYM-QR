<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * PRD §6.1 tabel 4. Tanpa hard delete; hanya out_of_service.
     */
    public function up(): void
    {
        DB::statement(<<<'SQL'
CREATE TABLE lockers (
    locker_id     INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    locker_number VARCHAR(10) NOT NULL,
    status        ENUM('available','occupied','out_of_service') NOT NULL DEFAULT 'available',
    note          VARCHAR(255) NULL,
    updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                            ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_lockers_number (locker_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('lockers');
    }
};
