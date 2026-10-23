<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\Support\InteractsWithAuth;
use Tests\Support\InteractsWithGym;
use Tests\TestCase;

/**
 * 3B: assign/return/no-locker/verify identity + daftar sesi & loker (FR-CAS-02..07).
 */
class LockerAssignmentTest extends TestCase
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

    public function test_assign_loker_pending_mengunci_loker_dan_menulis_audit(): void
    {
        [$member, $membershipId] = $this->memberWithMembership();
        $logId = $this->openSession($member, $membershipId);
        $lockerId = $this->createLocker('05', 'available');

        $response = $this->assign($logId, $lockerId);

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.log_id', $logId)
            ->assertJsonPath('data.locker_id', $lockerId)
            ->assertJsonPath('data.locker_number', '05')
            ->assertJsonPath('data.locker_state', 'assigned')
            ->assertJsonPath('data.member_name', 'John Doe');

        $log = DB::table('attendance_logs')->where('log_id', $logId)->first();
        $this->assertSame('assigned', $log->locker_state);
        $this->assertSame($lockerId, (int) $log->locker_id);
        $this->assertSame($this->staff->user_id, (int) $log->assigned_by_staff_id);
        $this->assertNotNull($log->assigned_at);

        $this->assertSame('occupied', DB::table('lockers')->where('locker_id', $lockerId)->value('status'));
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'locker.assigned',
            'entity_type' => 'attendance_log',
            'entity_id' => $logId,
        ]);
    }

    public function test_assign_loker_yang_sudah_terisi_ditolak_lock_er_taken(): void
    {
        [$member, $membershipId] = $this->memberWithMembership();
        $logId = $this->openSession($member, $membershipId);
        $lockerId = $this->createLocker('05', 'occupied');

        $this->assign($logId, $lockerId)
            ->assertStatus(409)
            ->assertJsonPath('code', 'LOCKER_TAKEN');

        $log = DB::table('attendance_logs')->where('log_id', $logId)->first();
        $this->assertSame('pending', $log->locker_state);
        $this->assertNull($log->locker_id);
    }

    public function test_assign_ulang_sesi_yang_sudah_punya_loker_ditolak(): void
    {
        [$member, $membershipId] = $this->memberWithMembership();
        $first = $this->createLocker('05', 'available');
        $second = $this->createLocker('06', 'available');
        $logId = $this->openSession($member, $membershipId);

        $this->assign($logId, $first)->assertOk();

        $this->assign($logId, $second)
            ->assertStatus(409)
            ->assertJsonPath('code', 'LOCKER_ALREADY_ASSIGNED');

        $this->assertSame('05', DB::table('lockers')->where('locker_id', $first)->value('locker_number'));
        $this->assertSame('available', DB::table('lockers')->where('locker_id', $second)->value('status'));
    }

    public function test_assign_pada_sesi_tertutup_ditolak(): void
    {
        [$member, $membershipId] = $this->memberWithMembership();
        $logId = $this->openSession($member, $membershipId, [
            'checkout_time' => now()->subMinutes(5),
            'checkout_type' => 'staff_checkout',
        ]);
        $lockerId = $this->createLocker('05', 'available');

        $this->assign($logId, $lockerId)
            ->assertStatus(409)
            ->assertJsonPath('code', 'SESSION_CLOSED');
    }

    public function test_return_loker_idempoten_tidak_menimpa_timestamp_pertama(): void
    {
        [$member, $membershipId] = $this->memberWithMembership();
        $lockerId = $this->createLocker('05', 'available');
        $logId = $this->openSession($member, $membershipId);

        $this->assign($logId, $lockerId)->assertOk();

        $first = $this->release($logId);
        $first->assertOk()
            ->assertJsonPath('data.already_returned', false)
            ->assertJsonPath('data.locker_number', '05');

        $log = DB::table('attendance_logs')->where('log_id', $logId)->first();
        $this->assertSame('returned', $log->locker_state);
        $this->assertSame('locker_returned', $log->checkout_type);
        $this->assertNotNull($log->checkout_time);
        $this->assertSame('available', DB::table('lockers')->where('locker_id', $lockerId)->value('status'));

        $second = $this->release($logId);
        $second->assertOk()->assertJsonPath('data.already_returned', true);

        $after = DB::table('attendance_logs')->where('log_id', $logId)->first();
        $this->assertSame((string) $log->checkout_time, (string) $after->checkout_time);
        $this->assertSame(1, DB::table('audit_logs')->where('action', 'locker.returned')->count());
    }

    public function test_tandai_tanpa_loker_mengubah_pending_ke_none_dan_audit(): void
    {
        [$member, $membershipId] = $this->memberWithMembership();
        $logId = $this->openSession($member, $membershipId);

        $this->patchJson("/api/v1/receptionist/attendance/{$logId}/no-locker")
            ->assertOk()
            ->assertJsonPath('data.locker_state', 'none');

        $log = DB::table('attendance_logs')->where('log_id', $logId)->first();
        $this->assertSame('none', $log->locker_state);
        $this->assertSame($this->staff->user_id, (int) $log->assigned_by_staff_id);
        $this->assertDatabaseHas('audit_logs', ['action' => 'attendance.no_locker', 'entity_id' => $logId]);
    }

    public function test_verifikasi_identitas_menyimpan_pelaku_dan_audit(): void
    {
        [$member, $membershipId] = $this->memberWithMembership();
        $logId = $this->openSession($member, $membershipId);

        $this->patchJson("/api/v1/receptionist/attendance/{$logId}/verify-identity")
            ->assertOk()
            ->assertJsonPath('data.already_verified', false);

        $log = DB::table('attendance_logs')->where('log_id', $logId)->first();
        $this->assertSame($this->staff->user_id, (int) $log->identity_verified_by);
        $this->assertNotNull($log->identity_verified_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'attendance.identity_verified', 'entity_id' => $logId]);
    }

    public function test_daftar_loker_menampilkan_status_dan_penghuni(): void
    {
        [$member, $membershipId] = $this->memberWithMembership();
        $logId = $this->openSession($member, $membershipId);
        $taken = $this->createLocker('05', 'available');
        $this->createLocker('06', 'available');
        $this->createLocker('07', 'out_of_service');
        $this->assign($logId, $taken)->assertOk();

        $response = $this->getJson('/api/v1/receptionist/lockers');

        $response->assertOk()
            ->assertJsonPath('data.summary.available', 1)
            ->assertJsonPath('data.summary.occupied', 1)
            ->assertJsonPath('data.summary.out_of_service', 1)
            ->assertJsonCount(3, 'data.lockers');

        $occupied = collect($response->json('data.lockers'))->firstWhere('status', 'occupied');
        $this->assertSame('05', $occupied['locker_number']);
        $this->assertSame('John Doe', $occupied['occupant']['full_name']);
    }

    public function test_daftar_sesi_filter_butuh_loker_dan_belum_verifikasi(): void
    {
        [$member, $membershipId] = $this->memberWithMembership();
        $pending = $this->openSession($member, $membershipId);

        $other = $this->createMember(['full_name' => 'Sari']);
        $otherMembership = $this->createMembership($other);
        $none = $this->openSession($other, $otherMembership, ['locker_state' => 'none']);

        $response = $this->getJson('/api/v1/receptionist/sessions?filter=need_locker');
        $response->assertOk()->assertJsonCount(1, 'data.sessions');
        $this->assertSame($pending, $response->json('data.sessions.0.log_id'));

        $unverified = $this->getJson('/api/v1/receptionist/sessions?filter=unverified');
        $unverified->assertOk()->assertJsonCount(2, 'data.sessions');

        $noneFilter = $this->getJson('/api/v1/receptionist/sessions?filter=no_locker');
        $noneFilter->assertOk()->assertJsonCount(1, 'data.sessions');
        $this->assertSame($none, $noneFilter->json('data.sessions.0.log_id'));
    }

    public function test_member_tidak_boleh_mengakses_endpoint_kasir(): void
    {
        $member = $this->createMember();

        $this->actingAs($member, 'staff')
            ->getJson('/api/v1/receptionist/lockers')
            ->assertStatus(403)
            ->assertJsonPath('code', 'FORBIDDEN_ROLE');
    }

    /**
     * @return array{0: User, 1: int}
     */
    private function memberWithMembership(): array
    {
        $member = $this->createMember(['full_name' => 'John Doe']);
        $membershipId = $this->createMembership($member);

        return [$member, $membershipId];
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function openSession(User $member, int $membershipId, array $overrides = []): int
    {
        return DB::table('attendance_logs')->insertGetId($overrides + [
            'user_id' => $member->user_id,
            'membership_id' => $membershipId,
            'locker_state' => 'pending',
            'checkin_time' => now()->subMinutes(10),
            'checkout_time' => null,
            'checkin_method' => 'qr_scan',
            'latitude' => 0,
            'longitude' => 0,
            'gps_accuracy_m' => 5,
        ]);
    }

    private function assign(int $logId, int $lockerId): TestResponse
    {
        return $this->patchJson("/api/v1/receptionist/attendance/{$logId}/assign-locker", [
            'locker_id' => $lockerId,
        ]);
    }

    private function release(int $logId): TestResponse
    {
        return $this->patchJson("/api/v1/receptionist/attendance/{$logId}/release-locker");
    }
}
