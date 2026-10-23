<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Support\InteractsWithAuth;
use Tests\Support\InteractsWithGym;
use Tests\TestCase;

/**
 * M4: administrasi paket, loker, gym settings, staf, batch reset, audit (FR-ADM, FR-CAS-09).
 */
class AdminManagementTest extends TestCase
{
    use InteractsWithAuth, InteractsWithGym, RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-10-23 02:00:00', 'UTC'));
        $this->fakeOtpSender();
        $this->createGymSettings();

        $this->admin = User::factory()->create([
            'phone_number' => '628222222222',
            'role' => 'admin',
        ]);

        $this->actingAs($this->admin, 'staff');
    }

    public function test_admin_membuat_dan_menonaktifkan_paket(): void
    {
        $created = $this->postJson('/api/v1/admin/packages', [
            'code' => 'MONTH-30',
            'name' => 'Bulanan 30 Hari',
            'type' => 'monthly_unlimited',
            'duration_days' => 30,
            'price' => 250000,
        ]);

        $created->assertCreated()->assertJsonPath('data.is_active', true);
        $packageId = $created->json('data.package_id');

        $this->patchJson("/api/v1/admin/packages/{$packageId}", [
            'name' => 'Bulanan 30 Hari (baru)',
            'price' => 275000,
            'is_active' => false,
        ])->assertOk()->assertJsonPath('data.is_active', false);

        $this->assertDatabaseHas('packages', ['package_id' => $packageId, 'price' => 275000]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'package.updated']);
    }

    public function test_admin_menambah_loker_dan_menandai_rusak_dengan_alasan(): void
    {
        $created = $this->postJson('/api/v1/admin/lockers', ['locker_number' => 'A-01']);
        $created->assertCreated()->assertJsonPath('data.status', 'available');
        $lockerId = $created->json('data.locker_id');

        $this->patchJson("/api/v1/admin/lockers/{$lockerId}", [
            'status' => 'out_of_service',
        ])->assertStatus(400)->assertJsonPath('code', 'VALIDATION_ERROR');

        $this->patchJson("/api/v1/admin/lockers/{$lockerId}", [
            'status' => 'out_of_service',
            'reason' => 'Engsel rusak',
        ])->assertOk()->assertJsonPath('data.status', 'out_of_service');

        $this->assertDatabaseHas('audit_logs', ['action' => 'locker.out_of_service']);
    }

    public function test_admin_mengubah_radius_dan_regenerasi_qr_membatalkan_payload_lama(): void
    {
        $this->patchJson('/api/v1/admin/gym-settings', [
            'geofence_radius_m' => 45,
            'closing_time' => '22:30',
        ])->assertOk()->assertJsonPath('data.geofence_radius_m', 45);

        $old = DB::table('gym_settings')->value('qr_payload');

        $response = $this->postJson('/api/v1/admin/gym-settings/regenerate-qr');
        $response->assertOk();

        $new = DB::table('gym_settings')->value('qr_payload');

        $this->assertNotSame($old, $new);
        $this->assertDatabaseHas('audit_logs', ['action' => 'gym.qr_regenerated']);
    }

    public function test_admin_membuat_staf_dan_menonaktifkan(): void
    {
        $created = $this->postJson('/api/v1/admin/staff', [
            'full_name' => 'Kasir Baru',
            'phone_number' => '08133333333',
            'role' => 'receptionist',
            'password' => 'rahasia-staf',
        ]);

        $created->assertCreated()->assertJsonPath('data.role', 'receptionist');
        $staffId = $created->json('data.user_id');

        $this->assertTrue(password_verify('rahasia-staf', (string) DB::table('users')->where('user_id', $staffId)->value('password_hash')));

        $this->patchJson("/api/v1/admin/staff/{$staffId}", ['is_active' => false])
            ->assertOk()
            ->assertJsonPath('data.is_active', false);
    }

    public function test_batch_reset_menolak_jumlah_salah_dan_menutup_sesi(): void
    {
        $member = $this->createMember();
        $membershipId = $this->createMembership($member);
        $lockerId = $this->createLocker('05', 'occupied');

        $logId = DB::table('attendance_logs')->insertGetId([
            'user_id' => $member->user_id,
            'membership_id' => $membershipId,
            'locker_id' => $lockerId,
            'locker_state' => 'assigned',
            'checkin_time' => now()->subHours(2),
            'checkin_method' => 'qr_scan',
            'latitude' => 0,
            'longitude' => 0,
            'gps_accuracy_m' => 5,
        ]);

        $this->postJson('/api/v1/receptionist/lockers/batch-reset', ['confirm_count' => 2])
            ->assertStatus(400)
            ->assertJsonPath('code', 'VALIDATION_ERROR');

        $this->postJson('/api/v1/receptionist/lockers/batch-reset', ['confirm_count' => 1])
            ->assertOk()
            ->assertJsonPath('data.closed_sessions', 1)
            ->assertJsonPath('data.released_lockers', 1);

        $log = DB::table('attendance_logs')->where('log_id', $logId)->first();
        $this->assertSame('auto_close', $log->checkout_type);
        $this->assertNotNull($log->checkout_time);
        $this->assertSame('returned', $log->locker_state);
        $this->assertSame('available', DB::table('lockers')->where('locker_id', $lockerId)->value('status'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'locker.batch_reset']);
    }

    public function test_audit_log_bisa_difilter(): void
    {
        DB::table('audit_logs')->insert([
            'actor_user_id' => $this->admin->user_id,
            'actor_role' => 'admin',
            'action' => 'package.created',
            'entity_type' => 'package',
            'entity_id' => 1,
            'created_at' => now(),
        ]);

        DB::table('audit_logs')->insert([
            'actor_user_id' => $this->admin->user_id,
            'actor_role' => 'admin',
            'action' => 'locker.updated',
            'entity_type' => 'locker',
            'entity_id' => 1,
            'created_at' => now(),
        ]);

        $this->getJson('/api/v1/admin/audit-logs?action=package.created')
            ->assertOk()
            ->assertJsonCount(1, 'data.logs')
            ->assertJsonPath('data.logs.0.action', 'package.created');
    }

    public function test_kasir_tidak_boleh_akses_admin(): void
    {
        $staff = User::factory()->create(['role' => 'receptionist', 'phone_number' => '628111111111']);

        $this->actingAs($staff, 'staff')
            ->getJson('/api/v1/admin/audit-logs')
            ->assertStatus(403)
            ->assertJsonPath('code', 'FORBIDDEN_ROLE');
    }

    public function test_admin_mereset_password_staf(): void
    {
        $staff = User::factory()->create(['role' => 'receptionist', 'phone_number' => '628111111111']);

        $this->patchJson("/api/v1/admin/staff/{$staff->user_id}", ['password' => 'password-baru'])
            ->assertOk();

        $hash = (string) DB::table('users')->where('user_id', $staff->user_id)->value('password_hash');
        $this->assertTrue(password_verify('password-baru', $hash));
    }
}
