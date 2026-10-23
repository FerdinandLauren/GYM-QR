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
 * M5: laporan kunjungan, peak hours, dan job kedaluwarsa membership (FR-ADM-03).
 */
class ReportsTest extends TestCase
{
    use InteractsWithAuth, InteractsWithGym, RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-10-23 02:00:00', 'UTC'));
        $this->fakeOtpSender();
        $this->createGymSettings();

        $this->admin = User::factory()->create(['role' => 'admin', 'phone_number' => '628222222222']);
        $this->actingAs($this->admin, 'staff');
    }

    public function test_laporan_harian_menghitung_sesi_member_unik_dan_durasi(): void
    {
        $first = $this->createMember(['phone_number' => '628100000001']);
        $second = $this->createMember(['phone_number' => '628100000002']);
        $membership = $this->createMembership($first);
        $otherMembership = $this->createMembership($second);

        $this->createSession($first, $membership, '2026-10-22 03:00:00', '2026-10-22 04:00:00');
        $this->createSession($first, $membership, '2026-10-22 10:00:00', '2026-10-22 11:30:00');
        $this->createSession($second, $otherMembership, '2026-10-23 02:00:00', null);

        $response = $this->getJson('/api/v1/admin/reports/attendance?from=2026-10-22&to=2026-10-23&group=day');

        $response->assertOk()->assertJsonCount(2, 'data.rows');
        $this->assertSame(2, $response->json('data.rows.0.sessions'));
        $this->assertSame(1, $response->json('data.rows.0.unique_members'));
        $this->assertSame(75.0, (float) $response->json('data.rows.0.avg_duration_minutes'));
        $this->assertSame(1, $response->json('data.rows.1.sessions'));
    }

    public function test_peak_hours_mengembalikan_24_bucket(): void
    {
        $member = $this->createMember();
        $membership = $this->createMembership($member);
        $this->createSession($member, $membership, '2026-10-22 10:00:00', '2026-10-22 11:00:00');

        $response = $this->getJson('/api/v1/admin/reports/peak-hours?from=2026-10-22&to=2026-10-22');

        $response->assertOk()->assertJsonCount(24, 'data.hours');

        $hour17 = collect($response->json('data.hours'))->firstWhere('hour', 17);
        $this->assertSame(1, $hour17['sessions']);
    }

    public function test_export_csv_mengembalikan_header_dan_baris(): void
    {
        $member = $this->createMember();
        $membership = $this->createMembership($member);
        $this->createSession($member, $membership, '2026-10-22 03:00:00', '2026-10-22 04:00:00');

        $response = $this->get('/api/v1/admin/reports/attendance?from=2026-10-22&to=2026-10-22&group=day&format=csv');

        $response->assertOk();
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('content-type'));
        $this->assertStringContainsString('period,sessions,unique_members,avg_duration_minutes', (string) $response->getContent());
    }

    public function test_command_menandai_membership_kedaluwarsa(): void
    {
        $member = $this->createMember();
        $expired = $this->createMembership($member, [
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-30',
        ]);
        $active = $this->createMembership($member, [
            'start_date' => '2026-10-01',
            'end_date' => '2026-11-30',
        ]);

        $this->artisan('memberships:expire')->assertSuccessful();

        $this->assertSame('expired', DB::table('memberships')->where('membership_id', $expired)->value('status'));
        $this->assertSame('active', DB::table('memberships')->where('membership_id', $active)->value('status'));
    }

    private function createSession(User $member, int $membershipId, string $checkin, ?string $checkout): void
    {
        DB::table('attendance_logs')->insert([
            'user_id' => $member->user_id,
            'membership_id' => $membershipId,
            'locker_state' => 'none',
            'checkin_time' => $checkin,
            'checkout_time' => $checkout,
            'checkin_method' => 'qr_scan',
            'latitude' => 0,
            'longitude' => 0,
            'gps_accuracy_m' => 5,
        ]);
    }
}
