<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Support\InteractsWithAuth;
use Tests\Support\InteractsWithGym;
use Tests\TestCase;

class MemberProfileTest extends TestCase
{
    use InteractsWithAuth, InteractsWithGym, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-10-23 02:00:00', 'UTC'));
        $this->fakeOtpSender();
        $this->createGymSettings();
    }

    public function test_profil_mengembalikan_membership_dan_sesi_aktif_dengan_nomor_loker(): void
    {
        $member = $this->createMember([
            'full_name' => 'John Doe',
            'phone_number' => '628123456789',
        ]);
        $membershipId = $this->createMembership($member, [
            'start_date' => '2026-10-06',
            'end_date' => '2026-11-04',
        ]);
        $lockerId = $this->createLocker('05');

        $this->createAttendance($member, $membershipId, [
            'checkin_time' => now()->subHour(),
            'checkout_time' => null,
            'locker_state' => 'assigned',
            'locker_id' => $lockerId,
        ]);

        $this->withToken($this->tokenFor($member))
            ->getJson('/api/v1/member/profile')
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.user.user_id', $member->user_id)
            ->assertJsonPath('data.user.full_name', 'John Doe')
            ->assertJsonPath('data.user.phone_masked', '6281****6789')
            ->assertJsonPath('data.membership.package_name', 'Bulanan Unlimited')
            ->assertJsonPath('data.membership.type', 'monthly_unlimited')
            ->assertJsonPath('data.membership.effective_status', 'active')
            ->assertJsonPath('data.membership.remaining_days', 12)
            ->assertJsonPath('data.membership.remaining_visits', null)
            ->assertJsonPath('data.membership.end_date', '2026-11-04')
            ->assertJsonPath('data.active_session.log_id', DB::table('attendance_logs')->value('log_id'))
            ->assertJsonPath('data.active_session.checkin_time', '2026-10-23T01:00:00Z')
            ->assertJsonPath('data.active_session.locker_state', 'assigned')
            ->assertJsonPath('data.active_session.locker_number', '05');
    }

    public function test_remaining_days_memakai_tanggal_jakarta_setelah_tengah_malam_wib(): void
    {
        $this->travelTo(Carbon::parse('2026-10-23 17:30:00', 'UTC'));

        $member = $this->createMember(['phone_number' => '628123456789']);
        $this->createMembership($member, [
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-25',
        ]);

        $this->withToken($this->tokenFor($member))
            ->getJson('/api/v1/member/profile')
            ->assertOk()
            ->assertJsonPath('data.membership.remaining_days', 1);
    }

    public function test_profil_menampilkan_membership_kedaluwarsa_dengan_status_expired(): void
    {
        $member = $this->createMember(['phone_number' => '628123456789']);
        $this->createMembership($member, [
            'start_date' => '2026-09-01',
            'end_date' => '2026-10-22',
        ]);

        $this->withToken($this->tokenFor($member))
            ->getJson('/api/v1/member/profile')
            ->assertOk()
            ->assertJsonPath('data.membership.effective_status', 'expired')
            ->assertJsonPath('data.membership.remaining_days', 0)
            ->assertJsonPath('data.active_session', null);
    }

    public function test_profil_day_pass_menampilkan_sisa_kuota(): void
    {
        $member = $this->createMember(['phone_number' => '628123456789']);
        $this->createMembership($member, ['type' => 'single_visit']);

        $this->withToken($this->tokenFor($member))
            ->getJson('/api/v1/member/profile')
            ->assertOk()
            ->assertJsonPath('data.membership.type', 'single_visit')
            ->assertJsonPath('data.membership.effective_status', 'active')
            ->assertJsonPath('data.membership.remaining_visits', 1)
            ->assertJsonPath('data.membership.remaining_days', null);
    }

    public function test_profil_tanpa_membership_mengembalikan_null(): void
    {
        $member = $this->createMember(['phone_number' => '628123456789']);

        $this->withToken($this->tokenFor($member))
            ->getJson('/api/v1/member/profile')
            ->assertOk()
            ->assertJsonPath('data.membership', null);
    }

    public function test_profil_tidak_menampilkan_sesi_aktif_member_lain(): void
    {
        $memberA = $this->createMember(['phone_number' => '628111111111']);
        $memberB = $this->createMember(['phone_number' => '628222222222']);
        $membershipB = $this->createMembership($memberB);
        $lockerId = $this->createLocker('07');

        $this->createAttendance($memberB, $membershipB, [
            'checkin_time' => now()->subMinutes(10),
            'checkout_time' => null,
            'locker_state' => 'assigned',
            'locker_id' => $lockerId,
        ]);

        $this->withToken($this->tokenFor($memberA))
            ->getJson('/api/v1/member/profile')
            ->assertOk()
            ->assertJsonPath('data.user.user_id', $memberA->user_id)
            ->assertJsonPath('data.active_session', null);
    }

    public function test_riwayat_hanya_menampilkan_30_kunjungan_terakhir_milik_member(): void
    {
        $memberA = $this->createMember(['phone_number' => '628111111111']);
        $memberB = $this->createMember(['phone_number' => '628222222222']);
        $membershipA = $this->createMembership($memberA);
        $membershipB = $this->createMembership($memberB);

        $idsA = [];
        for ($index = 1; $index <= 35; $index++) {
            $idsA[] = $this->createAttendance($memberA, $membershipA, [
                'checkin_time' => now()->subMinutes($index),
                'checkout_time' => now()->subMinutes($index)->addMinutes(20),
            ]);
        }

        $idB = $this->createAttendance($memberB, $membershipB, [
            'checkin_time' => now(),
            'checkout_time' => null,
        ]);

        $response = $this->withToken($this->tokenFor($memberA))
            ->getJson('/api/v1/member/attendance/history')
            ->assertOk()
            ->assertJsonCount(30, 'data')
            ->assertJsonStructure(['data' => ['*' => [
                'log_id',
                'checkin_time',
                'checkout_time',
                'locker_number',
            ]]]);

        $this->assertSame($idsA[0], $response->json('data.0.log_id'));
        $this->assertSame('2026-10-23T01:59:00Z', $response->json('data.0.checkin_time'));

        $returned = array_column($response->json('data'), 'log_id');

        $this->assertNotContains($idB, $returned);
        $this->assertCount(30, array_intersect($returned, $idsA));
    }

    public function test_profil_dan_riwayat_membutuhkan_token_member(): void
    {
        $this->getJson('/api/v1/member/profile')
            ->assertStatus(401)
            ->assertJsonPath('code', 'UNAUTHENTICATED');

        $this->getJson('/api/v1/member/attendance/history')
            ->assertStatus(401)
            ->assertJsonPath('code', 'UNAUTHENTICATED');
    }
}
