<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Support\InteractsWithAuth;
use Tests\Support\InteractsWithGym;
use Tests\TestCase;

/**
 * 3C: check-in manual, pencarian member, daftar scan ditolak (FR-CAS-05, FR-CAS-11).
 */
class ManualCheckInTest extends TestCase
{
    use InteractsWithAuth, InteractsWithGym, RefreshDatabase;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-10-23 11:00:00', 'UTC'));
        $this->fakeOtpSender();
        $this->createGymSettings();

        $this->staff = User::factory()->create([
            'full_name' => 'Kasir Satu',
            'phone_number' => '628111111111',
            'role' => 'receptionist',
        ]);

        $this->actingAs($this->staff, 'staff');
    }

    public function test_checkin_manual_menyimpan_tanpa_koordinat_dan_audit(): void
    {
        $member = $this->createMember(['full_name' => 'John Doe']);
        $membershipId = $this->createMembership($member);

        $response = $this->manual($member->user_id, $membershipId, (string) Str::uuid(), [
            'locker_intent' => 'need_locker',
            'reason' => 'battery_dead',
            'note' => 'HP mati, identitas dicek dari KTP',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.member_name', 'John Doe')
            ->assertJsonPath('data.checkin_method', 'manual_receptionist')
            ->assertJsonPath('data.locker_state', 'pending');

        $log = DB::table('attendance_logs')->where('log_id', $response->json('data.log_id'))->first();

        $this->assertSame('manual_receptionist', $log->checkin_method);
        $this->assertSame('battery_dead', $log->manual_reason);
        $this->assertNull($log->latitude);
        $this->assertNull($log->longitude);
        $this->assertNull($log->gps_accuracy_m);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'attendance.manual_checkin',
            'entity_type' => 'attendance_log',
            'entity_id' => $log->log_id,
        ]);
    }

    public function test_checkin_manual_wajib_idempotency_key(): void
    {
        $member = $this->createMember();
        $membershipId = $this->createMembership($member);

        $this->postJson('/api/v1/receptionist/manual-checkin', [
            'user_id' => $member->user_id,
            'membership_id' => $membershipId,
            'locker_intent' => 'no_locker',
            'reason' => 'no_phone',
        ])->assertStatus(400)->assertJsonPath('code', 'VALIDATION_ERROR');

        $this->assertSame(0, DB::table('attendance_logs')->count());
    }

    public function test_replay_key_sama_tidak_membuat_baris_kedua(): void
    {
        $member = $this->createMember();
        $membershipId = $this->createMembership($member);
        $key = (string) Str::uuid();

        $first = $this->manual($member->user_id, $membershipId, $key);
        $second = $this->manual($member->user_id, $membershipId, $key);

        $first->assertOk();
        $second->assertOk();

        $this->assertSame($first->json('data.log_id'), $second->json('data.log_id'));
        $this->assertSame(1, DB::table('attendance_logs')->count());
    }

    public function test_membership_tidak_aktif_ditolak(): void
    {
        $member = $this->createMember();
        $membershipId = $this->createMembership($member, [
            'start_date' => now()->timezone('Asia/Jakarta')->subDays(40)->toDateString(),
            'end_date' => now()->timezone('Asia/Jakarta')->subDays(10)->toDateString(),
        ]);

        $this->manual($member->user_id, $membershipId, (string) Str::uuid())
            ->assertStatus(403)
            ->assertJsonPath('code', 'MEMBERSHIP_EXPIRED');

        $this->assertSame(0, DB::table('attendance_logs')->count());
    }

    public function test_sesi_terbuka_menolak_checkin_manual(): void
    {
        $member = $this->createMember();
        $membershipId = $this->createMembership($member);

        DB::table('attendance_logs')->insert([
            'user_id' => $member->user_id,
            'membership_id' => $membershipId,
            'locker_state' => 'none',
            'checkin_time' => now()->subMinutes(20),
            'checkin_method' => 'qr_scan',
            'latitude' => 0,
            'longitude' => 0,
            'gps_accuracy_m' => 5,
        ]);

        $this->manual($member->user_id, $membershipId, (string) Str::uuid())
            ->assertStatus(409)
            ->assertJsonPath('code', 'SESSION_ACTIVE');
    }

    public function test_day_pass_mengurangi_kuota_satu_kali(): void
    {
        $member = $this->createMember();
        $membershipId = $this->createMembership($member, ['type' => 'single_visit']);

        $this->manual($member->user_id, $membershipId, (string) Str::uuid())->assertOk();

        $membership = DB::table('memberships')->where('membership_id', $membershipId)->first();

        $this->assertSame(0, (int) $membership->remaining_visits);
        $this->assertSame('expired', $membership->status);
    }

    public function test_cari_member_minimal_tiga_karakter_dan_menyertakan_status(): void
    {
        $member = $this->createMember(['full_name' => 'John Doe', 'phone_number' => '628123456789']);
        $this->createMembership($member);

        $this->getJson('/api/v1/receptionist/members?q=jo')
            ->assertStatus(400)
            ->assertJsonPath('code', 'VALIDATION_ERROR');

        $response = $this->getJson('/api/v1/receptionist/members?q=John');

        $response->assertOk()->assertJsonCount(1, 'data.members');
        $this->assertSame($member->user_id, $response->json('data.members.0.user_id'));
        $this->assertSame('active', $response->json('data.members.0.memberships.0.effective_status'));
        $this->assertStringContainsString('****', $response->json('data.members.0.phone_masked'));

        $this->assertSame($member->user_id, $this->getJson('/api/v1/receptionist/members?q=628123')->json('data.members.0.user_id'));
    }

    public function test_member_soft_deleted_tidak_muncul_di_pencarian(): void
    {
        $member = $this->createMember(['full_name' => 'Dodi']);
        $this->createMembership($member);
        $member->delete();

        $this->getJson('/api/v1/receptionist/members?q=Dodi')
            ->assertOk()
            ->assertJsonCount(0, 'data.members');
    }

    public function test_scan_ditolak_terakhir_muncul_di_daftar(): void
    {
        $member = $this->createMember(['full_name' => 'John Doe']);

        DB::table('audit_logs')->insert([
            'actor_user_id' => $member->user_id,
            'actor_role' => 'member',
            'action' => 'attendance.scan_rejected',
            'entity_type' => 'user',
            'entity_id' => $member->user_id,
            'after_json' => json_encode(['reason_code' => 'MEMBERSHIP_EXPIRED']),
            'created_at' => now(),
        ]);

        $response = $this->getJson('/api/v1/receptionist/rejected-attempts');

        $response->assertOk()->assertJsonCount(1, 'data.attempts');
        $this->assertSame('John Doe', $response->json('data.attempts.0.full_name'));
        $this->assertSame('MEMBERSHIP_EXPIRED', $response->json('data.attempts.0.reason_code'));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function manual(int $userId, int $membershipId, string $key, array $overrides = []): TestResponse
    {
        return $this->postJson(
            '/api/v1/receptionist/manual-checkin',
            $overrides + [
                'user_id' => $userId,
                'membership_id' => $membershipId,
                'locker_intent' => 'no_locker',
                'reason' => 'no_phone',
            ],
            ['Idempotency-Key' => $key],
        );
    }
}
