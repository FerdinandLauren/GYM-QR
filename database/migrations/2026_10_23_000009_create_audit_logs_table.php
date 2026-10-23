<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * PRD §6.1 tabel 8. Tanpa ON DELETE CASCADE: audit harus bertahan.
     */
    public function up(): void
    {
        DB::statement(<<<'SQL'
CREATE TABLE audit_logs (
    audit_id      BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    actor_user_id INT UNSIGNED NULL,
    actor_role    VARCHAR(20)  NULL,
    action        VARCHAR(60)  NOT NULL,
    entity_type   VARCHAR(30)  NULL,
    entity_id     BIGINT UNSIGNED NULL,
    before_json   JSON NULL,
    after_json    JSON NULL,
    request_ip    VARBINARY(16) NULL,
    created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_audit_action (action, created_at),
    KEY idx_audit_actor (actor_user_id, created_at),
    CONSTRAINT fk_audit_actor FOREIGN KEY (actor_user_id) REFERENCES users(user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
