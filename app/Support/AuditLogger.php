<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Menulis audit_logs (PRD §6.1 tabel 9). Dipakai untuk aksi wajib audit AGENTS §5.14.
 */
final class AuditLogger
{
    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    public function log(
        ?User $actor,
        string $action,
        ?string $entityType = null,
        ?int $entityId = null,
        ?array $before = null,
        ?array $after = null,
    ): void {
        DB::table('audit_logs')->insert([
            'actor_user_id' => $actor?->user_id,
            'actor_role' => $actor?->role,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'before_json' => $before === null ? null : json_encode($before, JSON_UNESCAPED_UNICODE),
            'after_json' => $after === null ? null : json_encode($after, JSON_UNESCAPED_UNICODE),
            'request_ip' => $this->packedIp(),
            'created_at' => now(),
        ]);
    }

    private function packedIp(): ?string
    {
        $ip = request()->ip();

        if ($ip === null) {
            return null;
        }

        $packed = inet_pton($ip);

        return $packed === false ? null : $packed;
    }
}
