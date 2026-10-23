<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\InteractsWithAuth;
use Tests\Support\InteractsWithGym;
use Tests\TestCase;

class CheckInScanTest extends TestCase
{
    use InteractsWithAuth, InteractsWithGym, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-10-23 02:00:00', 'UTC'));
        $this->fakeOtpSender();
        $this->createGymSettings();
    }

    public function test_checkin_qr_aktif_membuat_satu_baris_dan_mengembalikan_data_sesi(): void
    {
        $member = $this->member();
        $this->createMembership($member);

        $response = $this->postScan($member, $this->scanPayload(['locker_intent' => 'need_locker']), (string) Str::uuid());

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('message', 'Check-in berhasil. Silakan konfirmasi penggunaan loker ke kasir.')
            ->assertJsonPath('data.checkin_time', '2026-10-23T02:00:00Z')
            ->assertJsonPath('data.member_name', 'John Doe')
            ->assertJsonPath('data.membership_type', 'monthly_unlimited')
            ->assertJsonPath('data.locker_state', 'pending')
            ->assertJsonPath('data.locker_number', null);

        $this->assertIsInt($response->json('data.log_id'));

        $log = DB::table('attendance_logs')->first();

        $this->assertNotNull($log);
        $this->assertSame('2026-10-23 02:00:00', $log->checkin_time);
        $this->assertSame('qr_scan', $log->checkin_method);
        $this->assertSame('pending', $log->locker_state);
        $this->assertSame(0.0, (float) $log->latitude);
        $this->assertSame(0.0, (float) $log->longitude);
        $this->assertSame(10.0, (float) $log->gps_accuracy_m);
        $this->assertSame(1, DB::table('attendance_logs')->count());
    }

    public function test_qr_asing_ditolak_tanpa_membuat_baris_dan_tercatat_di_audit(): void
    {
        $member = $this->member();
        $this->createMembership($member);

        $this->postScan($member, $this->scanPayload(['qr_payload' => 'QR-ASING']), (string) Str::uuid())
            ->assertStatus(400)
            ->assertJsonPath('code', 'INVALID_QR')
            ->assertJsonPath('status', 'error');

        $this->assertSame(0, DB::table('attendance_logs')->count());

        $audit = DB::table('audit_logs')->where('action', 'attendance.scan_rejected')->first();

        $this->assertNotNull($audit);
        $this->assertSame($member->user_id, (int) $audit->actor_user_id);
        $this->assertSame('INVALID_QR', json_decode((string) $audit->after_json, true)['reason_code']);
    }

    public function test_scan_dari_lima_ratus_meter_ditolak_geo_out_of_range(): void
    {
        $member = $this->member();
        $this->createMembership($member);

        $this->postScan($member, $this->scanPayload(['latitude' => $this->latitudeOffset(500.0)]), (string) Str::uuid())
            ->assertStatus(403)
            ->assertJsonPath('code', 'GEO_OUT_OF_RANGE')
            ->assertJsonPath('meta.details.radius_m', 30);

        $this->assertSame(0, DB::table('attendance_logs')->count());
        $this->assertSame(
            'GEO_OUT_OF_RANGE',
            json_decode((string) DB::table('audit_logs')->where('action', 'attendance.scan_rejected')->value('after_json'), true)['reason_code'],
        );
    }

    public function test_accuracy_di_atas_batas_ditolak_gps_accuracy_low(): void
    {
        $member = $this->member();
        $this->createMembership($member);

        $this->postScan($member, $this->scanPayload(['accuracy' => 300]), (string) Str::uuid())
            ->assertStatus(422)
            ->assertJsonPath('code', 'GPS_ACCURACY_LOW');

        $this->assertSame(0, DB::table('attendance_logs')->count());
        $this->assertSame(
            'GPS_ACCURACY_LOW',
            json_decode((string) DB::table('audit_logs')->where('action', 'attendance.scan_rejected')->value('after_json'), true)['reason_code'],
        );
    }

    public function test_batas_geofence_persis_lolos_dan_titik_di_atasnya_ditolak(): void
    {
        $member = $this->member();
        $this->createMembership($member);

        $this->postScan($member, $this->scanPayload(['latitude' => $this->latitudeOffset(40.01)]), (string) Str::uuid())
            ->assertStatus(403)
            ->assertJsonPath('code', 'GEO_OUT_OF_RANGE');

        $this->postScan($member, $this->scanPayload(['latitude' => $this->latitudeOffset(40.0)]), (string) Str::uuid())
            ->assertOk();

        $this->assertSame(1, DB::table('attendance_logs')->count());
    }

    public function test_scan_saat_sesi_terbuka_ditolak_dengan_info_jam_dan_loker(): void
    {
        $member = $this->member();
        $membershipId = $this->createMembership($member);
        $lockerId = $this->createLocker('05');

        $this->createAttendance($member, $membershipId, [
            'checkin_time' => now()->subMinutes(20),
            'checkout_time' => null,
            'locker_state' => 'assigned',
            'locker_id' => $lockerId,
        ]);

        $this->postScan($member, $this->scanPayload(), (string) Str::uuid())
            ->assertStatus(409)
            ->assertJsonPath('code', 'SESSION_ACTIVE')
            ->assertJsonPath('meta.details.checkin_time', '2026-10-23T01:40:00Z')
            ->assertJsonPath('meta.details.locker_number', '05');

        $this->assertSame(1, DB::table('attendance_logs')->count());
    }

    public function test_rescan_kurang_lima_menit_setelah_sesi_ditutup_ditolak(): void
    {
        $member = $this->member();
        $membershipId = $this->createMembership($member);

        $this->createAttendance($member, $membershipId, [
            'checkin_time' => now()->subMinutes(30),
            'checkout_time' => now()->subMinute(),
        ]);

        $response = $this->postScan($member, $this->scanPayload(), (string) Str::uuid())
            ->assertStatus(429)
            ->assertJsonPath('code', 'RESCAN_GUARD');

        $retryAfter = (int) $response->json('meta.details.retry_after_seconds');

        $this->assertGreaterThan(0, $retryAfter);
        $this->assertLessThanOrEqual(300, $retryAfter);
        $this->assertSame(1, DB::table('attendance_logs')->count());
    }

    public function test_idempotency_key_sama_mengembalikan_respons_identik_tanpa_baris_baru(): void
    {
        $member = $this->member();
        $this->createMembership($member);
        $key = (string) Str::uuid();
        $payload = $this->scanPayload();

        $first = $this->postScan($member, $payload, $key)->assertOk()->json('data');
        $second = $this->postScan($member, $payload, $key)->assertOk()->json('data');

        $this->assertSame($first, $second);
        $this->assertSame(1, DB::table('attendance_logs')->count());
    }

    public function test_idempotency_key_wajib_dikirim(): void
    {
        $member = $this->member();
        $this->createMembership($member);

        $this->withToken($this->tokenFor($member))
            ->postJson('/api/v1/attendance/scan-qr', $this->scanPayload())
            ->assertStatus(400)
            ->assertJsonPath('code', 'VALIDATION_ERROR')
            ->assertJsonPath('meta.details.idempotency_key.0', 'Header Idempotency-Key wajib diisi.');
    }

    public function test_member_expired_ditolak_tanpa_baris_dan_tercatat_di_audit(): void
    {
        $member = $this->member();
        $this->createMembership($member, [
            'start_date' => '2026-09-01',
            'end_date' => '2026-10-22',
        ]);

        $this->postScan($member, $this->scanPayload(), (string) Str::uuid())
            ->assertStatus(403)
            ->assertJsonPath('code', 'MEMBERSHIP_EXPIRED');

        $this->assertSame(0, DB::table('attendance_logs')->count());

        $audit = DB::table('audit_logs')->where('action', 'attendance.scan_rejected')->first();

        $this->assertNotNull($audit);
        $this->assertSame('MEMBERSHIP_EXPIRED', json_decode((string) $audit->after_json, true)['reason_code']);
    }

    public function test_membership_suspended_ditolak_membership_inactive(): void
    {
        $member = $this->member();
        $this->createMembership($member, ['status' => 'suspended']);

        $this->postScan($member, $this->scanPayload(), (string) Str::uuid())
            ->assertStatus(403)
            ->assertJsonPath('code', 'MEMBERSHIP_INACTIVE');

        $this->assertSame(0, DB::table('attendance_logs')->count());
    }

    public function test_day_pass_hanya_dipakai_sekali_dan_kuota_berkurang_dalam_transaksi(): void
    {
        $member = $this->member();
        $membershipId = $this->createMembership($member, ['type' => 'single_visit']);

        $this->postScan($member, $this->scanPayload(['latitude' => $this->latitudeOffset(500.0)]), (string) Str::uuid())
            ->assertStatus(403);

        $this->assertSame(1, (int) DB::table('memberships')->where('membership_id', $membershipId)->value('remaining_visits'));

        $key = (string) Str::uuid();
        $first = $this->postScan($member, $this->scanPayload(), $key)->assertOk()->json('data');

        $this->assertSame(0, (int) DB::table('memberships')->where('membership_id', $membershipId)->value('remaining_visits'));
        $this->assertSame('expired', DB::table('memberships')->where('membership_id', $membershipId)->value('status'));

        $this->postScan($member, $this->scanPayload(), $key)
            ->assertOk()
            ->assertJsonPath('data.log_id', $first['log_id']);

        $this->assertSame(0, (int) DB::table('memberships')->where('membership_id', $membershipId)->value('remaining_visits'));
        $this->assertSame(1, DB::table('attendance_logs')->count());

        DB::table('attendance_logs')->update([
            'checkin_time' => now()->subMinutes(20),
            'checkout_time' => now()->subMinutes(10),
        ]);

        $this->postScan($member, $this->scanPayload(), (string) Str::uuid())
            ->assertStatus(403)
            ->assertJsonPath('code', 'MEMBERSHIP_EXPIRED');

        $this->travelTo(now()->addDay());

        $this->postScan($member, $this->scanPayload(), (string) Str::uuid())
            ->assertStatus(403)
            ->assertJsonPath('code', 'MEMBERSHIP_EXPIRED');

        $this->assertSame(1, DB::table('attendance_logs')->count());
    }

    public function test_day_pass_dipilih_sebelum_membership_bulanan(): void
    {
        $member = $this->member();
        $monthlyId = $this->createMembership($member);
        $dayPassId = $this->createMembership($member, ['type' => 'single_visit']);

        $this->postScan($member, $this->scanPayload(), (string) Str::uuid())->assertOk();

        $this->assertSame($dayPassId, (int) DB::table('attendance_logs')->value('membership_id'));
        $this->assertSame(0, (int) DB::table('memberships')->where('membership_id', $dayPassId)->value('remaining_visits'));
        $this->assertNull(DB::table('memberships')->where('membership_id', $monthlyId)->value('remaining_visits'));
    }

    public function test_bulanan_dengan_end_date_terdekat_dipilih(): void
    {
        $member = $this->member();
        $farId = $this->createMembership($member, [
            'start_date' => '2026-10-01',
            'end_date' => '2026-11-20',
        ]);
        $nearId = $this->createMembership($member, [
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-30',
        ]);

        $this->postScan($member, $this->scanPayload(), (string) Str::uuid())->assertOk();

        $this->assertSame($nearId, (int) DB::table('attendance_logs')->value('membership_id'));
        $this->assertNotSame($farId, (int) DB::table('attendance_logs')->value('membership_id'));
    }

    public function test_checkin_pada_2330_wib_tersimpan_utc(): void
    {
        $this->travelTo(Carbon::parse('2026-10-23 16:30:00', 'UTC'));

        $member = $this->member();
        $this->createMembership($member, [
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-31',
        ]);

        $this->postScan($member, $this->scanPayload(), (string) Str::uuid())
            ->assertOk()
            ->assertJsonPath('data.checkin_time', '2026-10-23T16:30:00Z');

        $this->assertSame('2026-10-23 16:30:00', DB::table('attendance_logs')->value('checkin_time'));
    }

    public function test_locker_intent_no_locker_menghasilkan_state_none(): void
    {
        $member = $this->member();
        $this->createMembership($member);

        $this->postScan($member, $this->scanPayload(['locker_intent' => 'no_locker']), (string) Str::uuid())
            ->assertOk()
            ->assertJsonPath('data.locker_state', 'none');

        $this->assertSame('none', DB::table('attendance_logs')->value('locker_state'));
    }

    public function test_koordinat_kosong_ditolak_validation_error(): void
    {
        $member = $this->member();
        $this->createMembership($member);

        $this->postScan($member, $this->scanPayload(['latitude' => '', 'longitude' => '']), (string) Str::uuid())
            ->assertStatus(400)
            ->assertJsonPath('code', 'VALIDATION_ERROR')
            ->assertJsonStructure(['meta' => ['details' => ['latitude', 'longitude']]]);

        $this->assertSame(0, DB::table('attendance_logs')->count());
    }

    public function test_scan_membutuhkan_token_member(): void
    {
        $this->postJson('/api/v1/attendance/scan-qr', $this->scanPayload(), ['Idempotency-Key' => (string) Str::uuid()])
            ->assertStatus(401)
            ->assertJsonPath('code', 'UNAUTHENTICATED');
    }

    private function member(): User
    {
        return $this->createMember([
            'full_name' => 'John Doe',
            'phone_number' => '628123456789',
        ]);
    }
}
