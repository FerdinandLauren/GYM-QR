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
 * M4: penjualan/perpanjangan paket dan aksi admin pada membership (PRD Â§4.3).
 */
class MembershipManagementTest extends TestCase
{
    use InteractsWithAuth, InteractsWithGym, RefreshDatabase;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-10-23 02:00:00', 'UTC'));
        $this->fakeOtpSender();
        $this->createGymSettings();

        $this->staff = User::factory()->create([
            'phone_number' => '628111111111',
            'role' => 'receptionist',
        ]);

        $this->actingAs($this->staff, 'staff');
    }

    public function test_daftar_member_baru_dan_jual_paket_bulanan(): void
    {
        $packageId = $this->createPackage(['name' => 'Bulanan Unlimited', 'duration_days' => 30]);

        $response = $this->postJson('/api/v1/receptionist/memberships', [
            'action' => 'new',
            'phone_number' => '08123456789',
            'full_name' => 'John Doe',
            'photo_url' => 'https://cdn.example/john.jpg',
            'package_id' => $packageId,
            'consent' => true,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.user.full_name', 'John Doe')
            ->assertJsonPath('data.user.phone_number', '628123456789')
            ->assertJsonPath('data.membership.type', 'monthly_unlimited')
            ->assertJsonPath('data.membership.status', 'active');

        $user = User::query()->where('phone_number', '628123456789')->first();
        $this->assertNotNull($user);
        $this->assertSame('member', $user->role);
        $this->assertNotNull($user->consent_at);

        $membership = DB::table('memberships')->where('membership_id', $response->json('data.membership.membership_id'))->first();
        $this->assertSame($this->staff->user_id, (int) $membership->sold_by_staff_id);
        $this->assertSame('2026-10-23', (string) $membership->start_date);
        $this->assertSame('2026-11-21', (string) $membership->end_date);
        $this->assertDatabaseHas('audit_logs', ['action' => 'membership.created']);
    }

    public function test_member_baru_wajib_foto_dan_consent(): void
    {
        $packageId = $this->createPackage();

        $this->postJson('/api/v1/receptionist/memberships', [
            'action' => 'new',
            'phone_number' => '08123456789',
            'full_name' => 'John Doe',
            'package_id' => $packageId,
        ])->assertStatus(400)->assertJsonPath('code', 'VALIDATION_ERROR');

        $this->assertSame(0, User::query()->where('role', 'member')->count());
    }

    public function test_nomor_sudah_terdaftar_tidak_bisa_dibuat_ulang(): void
    {
        $existing = $this->createMember(['phone_number' => '628123456789']);
        $this->createMembership($existing);
        $packageId = $this->createPackage();

        $this->postJson('/api/v1/receptionist/memberships', [
            'action' => 'new',
            'phone_number' => '628123456789',
            'full_name' => 'John Doe',
            'photo_url' => 'https://cdn.example/john.jpg',
            'package_id' => $packageId,
            'consent' => true,
        ])->assertStatus(400)->assertJsonPath('code', 'VALIDATION_ERROR');
    }

    public function test_perpanjangan_memakai_basis_end_date_berjalan(): void
    {
        $member = $this->createMember(['phone_number' => '628123456789']);
        $this->createMembership($member, [
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-31',
        ]);
        $packageId = $this->createPackage(['duration_days' => 30]);

        $response = $this->postJson('/api/v1/receptionist/memberships', [
            'action' => 'renew',
            'phone_number' => '628123456789',
            'package_id' => $packageId,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.membership.start_date', '2026-10-31')
            ->assertJsonPath('data.membership.end_date', '2026-11-29');

        $this->assertDatabaseHas('audit_logs', ['action' => 'membership.renewed']);
    }

    public function test_day_pass_mendapat_satu_kunjungan(): void
    {
        $packageId = $this->createPackage([
            'name' => 'Day Pass',
            'type' => 'single_visit',
            'duration_days' => 1,
            'visit_quota' => 1,
        ]);

        $response = $this->postJson('/api/v1/receptionist/memberships', [
            'action' => 'new',
            'phone_number' => '08123456789',
            'full_name' => 'Visitor',
            'photo_url' => 'https://cdn.example/v.jpg',
            'package_id' => $packageId,
            'consent' => true,
        ]);

        $response->assertCreated()->assertJsonPath('data.membership.remaining_visits', 1);

        $membership = DB::table('memberships')->where('membership_id', $response->json('data.membership.membership_id'))->first();
        $this->assertSame('2026-10-23', (string) $membership->start_date);
        $this->assertSame('2026-10-23', (string) $membership->end_date);
    }

    public function test_admin_suspend_dan_aktifkan_kembali(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'phone_number' => '628222222222']);
        $member = $this->createMember();
        $membershipId = $this->createMembership($member);

        $this->actingAs($admin, 'staff')
            ->patchJson("/api/v1/admin/memberships/{$membershipId}", [
                'action' => 'suspend',
                'reason' => 'Indikasi penyalahgunaan',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'suspended');

        $this->assertDatabaseHas('audit_logs', ['action' => 'membership.suspended']);

        $this->actingAs($admin, 'staff')
            ->patchJson("/api/v1/admin/memberships/{$membershipId}", [
                'action' => 'reactivate',
                'reason' => 'Klarifikasi selesai',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'active');
    }

    public function test_admin_tidak_bisa_aktifkan_membership_kedaluwarsa(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'phone_number' => '628222222222']);
        $member = $this->createMember();
        $membershipId = $this->createMembership($member, [
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-30',
            'status' => 'suspended',
        ]);

        $this->actingAs($admin, 'staff')
            ->patchJson("/api/v1/admin/memberships/{$membershipId}", [
                'action' => 'reactivate',
                'reason' => 'Coba aktifkan',
            ])
            ->assertStatus(400)
            ->assertJsonPath('code', 'VALIDATION_ERROR');
    }
}
