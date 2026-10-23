<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AuditLoggerTest extends TestCase
{
    use RefreshDatabase;

    public function test_mencatat_aktor_entity_dan_perubahan(): void
    {
        $actor = User::factory()->create(['role' => 'admin']);

        app(AuditLogger::class)->log(
            $actor,
            'locker.assigned',
            'locker',
            3,
            ['status' => 'available'],
            ['status' => 'occupied'],
        );

        $row = DB::table('audit_logs')->first();

        $this->assertSame($actor->user_id, (int) $row->actor_user_id);
        $this->assertSame('admin', $row->actor_role);
        $this->assertSame('locker.assigned', $row->action);
        $this->assertSame('locker', $row->entity_type);
        $this->assertSame(3, (int) $row->entity_id);
        $this->assertSame(['status' => 'available'], json_decode($row->before_json, true));
        $this->assertSame(['status' => 'occupied'], json_decode($row->after_json, true));
    }
}
