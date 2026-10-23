<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PragmaRX\Google2FA\Google2FA;
use Symfony\Component\HttpFoundation\Cookie;
use Tests\TestCase;

class StaffAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_staf_berhasil_dan_cookie_ketat(): void
    {
        $staff = $this->staff();

        $response = $this->postJson('/api/v1/receptionist/auth/login', [
            'phone_number' => '08123456789',
            'password' => 'rahasia-staf',
        ]);

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.user.user_id', $staff->user_id)
            ->assertJsonPath('data.user.role', 'receptionist')
            ->assertJsonPath('data.expires_in', 28800);

        $cookie = $this->sessionCookie($response);

        $this->assertNotNull($cookie);
        $this->assertTrue($cookie->isSecure());
        $this->assertTrue($cookie->isHttpOnly());
        $this->assertSame('strict', $cookie->getSameSite());

        $this->assertAuthenticatedAs($staff, 'staff');
    }

    public function test_login_password_salah_ditolak(): void
    {
        $this->staff();

        $this->postJson('/api/v1/receptionist/auth/login', [
            'phone_number' => '628123456789',
            'password' => 'password-salah',
        ])
            ->assertStatus(401)
            ->assertJsonPath('code', 'UNAUTHENTICATED');

        $this->assertGuest('staff');
    }

    public function test_login_member_tidak_bisa_masuk_area_staf(): void
    {
        User::factory()->create(['phone_number' => '628123456789']);

        $this->postJson('/api/v1/receptionist/auth/login', [
            'phone_number' => '628123456789',
            'password' => 'rahasia-staf',
        ])
            ->assertStatus(401)
            ->assertJsonPath('code', 'UNAUTHENTICATED');
    }

    public function test_admin_wajib_totp(): void
    {
        $google2fa = new Google2FA;
        $secret = $google2fa->generateSecretKey();
        $this->staff(['role' => 'admin', 'totp_secret' => $secret]);

        $payload = ['phone_number' => '628123456789', 'password' => 'rahasia-staf'];

        $this->postJson('/api/v1/receptionist/auth/login', $payload)
            ->assertStatus(401)
            ->assertJsonPath('code', 'UNAUTHENTICATED');

        $this->postJson('/api/v1/receptionist/auth/login', $payload + ['totp_code' => '000000'])
            ->assertStatus(401)
            ->assertJsonPath('code', 'UNAUTHENTICATED');
    }

    public function test_admin_dengan_totp_benar_berhasil_login(): void
    {
        $google2fa = new Google2FA;
        $secret = $google2fa->generateSecretKey();
        $this->staff(['role' => 'admin', 'totp_secret' => $secret]);

        $this->postJson('/api/v1/receptionist/auth/login', [
            'phone_number' => '628123456789',
            'password' => 'rahasia-staf',
            'totp_code' => $google2fa->getCurrentOtp($secret),
        ])
            ->assertOk()
            ->assertJsonPath('data.user.role', 'admin');
    }

    public function test_admin_tanpa_totp_secret_ditolak(): void
    {
        $this->staff(['role' => 'admin', 'totp_secret' => null]);

        $this->postJson('/api/v1/receptionist/auth/login', [
            'phone_number' => '628123456789',
            'password' => 'rahasia-staf',
            'totp_code' => '123456',
        ])
            ->assertStatus(401)
            ->assertJsonPath('code', 'UNAUTHENTICATED');
    }

    public function test_staf_tidak_aktif_ditolak(): void
    {
        $this->staff(['is_active' => false]);

        $this->postJson('/api/v1/receptionist/auth/login', [
            'phone_number' => '628123456789',
            'password' => 'rahasia-staf',
        ])
            ->assertStatus(401);
    }

    public function test_rate_limit_login_staf_lima_per_lima_belas_menit(): void
    {
        $this->staff();

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->postJson('/api/v1/receptionist/auth/login', [
                'phone_number' => '628123456789',
                'password' => 'password-salah',
            ])->assertStatus(401);
        }

        $this->postJson('/api/v1/receptionist/auth/login', [
            'phone_number' => '628123456789',
            'password' => 'password-salah',
        ])
            ->assertStatus(429)
            ->assertJsonPath('code', 'RATE_LIMITED')
            ->assertJsonStructure(['meta' => ['details' => ['retry_after_seconds']]]);
    }

    public function test_rate_limit_login_staf_per_ip(): void
    {
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->postJson('/api/v1/receptionist/auth/login', [
                'phone_number' => sprintf('62812345%04d', $attempt),
                'password' => 'password-salah',
            ])->assertStatus(401);
        }

        $this->postJson('/api/v1/receptionist/auth/login', [
            'phone_number' => '628123459999',
            'password' => 'password-salah',
        ])
            ->assertStatus(429)
            ->assertJsonPath('code', 'RATE_LIMITED');
    }

    public function test_logout_staf_mengakhiri_sesi(): void
    {
        $staff = $this->staff();

        $this->actingAs($staff, 'staff')
            ->postJson('/api/v1/receptionist/auth/logout')
            ->assertOk()
            ->assertJsonPath('status', 'success');

        $this->assertGuest('staff');
    }

    public function test_logout_staf_tanpa_sesi_ditolak(): void
    {
        $this->postJson('/api/v1/receptionist/auth/logout')
            ->assertStatus(401)
            ->assertJsonPath('code', 'UNAUTHENTICATED');
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function staff(array $attributes = []): User
    {
        return User::factory()->create($attributes + [
            'phone_number' => '628123456789',
            'role' => 'receptionist',
            'password_hash' => 'rahasia-staf',
        ]);
    }

    private function sessionCookie(TestResponse $response): ?Cookie
    {
        foreach ($response->headers->getCookies() as $cookie) {
            if ($cookie->getName() === config('session.cookie')) {
                return $cookie;
            }
        }

        return null;
    }
}
