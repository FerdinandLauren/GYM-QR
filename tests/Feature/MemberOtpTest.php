<?php

namespace Tests\Feature;

use App\Models\User;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\Support\InteractsWithAuth;
use Tests\TestCase;

class MemberOtpTest extends TestCase
{
    use InteractsWithAuth, RefreshDatabase;

    public function test_request_otp_menyimpan_hash_bukan_plaintext(): void
    {
        $sender = $this->fakeOtpSender();
        User::factory()->create(['email' => 'john@example.com']);

        $response = $this->postJson('/api/v1/auth/request-otp', ['email' => 'john@example.com']);

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.expires_in', 300)
            ->assertJsonMissingPath('data.code');

        $row = DB::table('otp_requests')->where('email', 'john@example.com')->first();

        $this->assertNotNull($row);
        $this->assertNotSame($sender->lastCodeFor('john@example.com'), $row->code_hash);
        $this->assertTrue(Hash::check($sender->lastCodeFor('john@example.com'), $row->code_hash));
        $this->assertSame(0, (int) $row->attempts);
        $this->assertTrue(Carbon::parse($row->expires_at)->between(
            now()->addMinutes(4)->addSeconds(50),
            now()->addMinutes(5)->addSeconds(10),
        ));
    }

    public function test_request_otp_memberi_respons_seragam_untuk_email_terdaftar_dan_tidak(): void
    {
        $this->fakeOtpSender();
        User::factory()->create(['email' => 'a@example.com']);

        $registered = $this->postJson('/api/v1/auth/request-otp', ['email' => 'a@example.com'])
            ->assertOk()
            ->json();

        $unknown = $this->postJson('/api/v1/auth/request-otp', ['email' => 'unknown@example.com'])
            ->assertOk()
            ->json();

        $this->assertSame($registered['status'], $unknown['status']);
        $this->assertSame($registered['message'], $unknown['message']);
        $this->assertSame($registered['data'], $unknown['data']);
        $this->assertSame(1, DB::table('otp_requests')->count());
    }

    public function test_request_otp_menahan_kirim_ulang_dalam_60_detik(): void
    {
        $sender = $this->fakeOtpSender();
        User::factory()->create(['email' => 'john@example.com']);

        $this->requestOtpFor('john@example.com');
        $this->requestOtpFor('john@example.com');

        $this->assertSame(1, $sender->countFor('john@example.com'));
        $this->assertSame(1, DB::table('otp_requests')->count());
    }

    public function test_verify_otp_mengeluarkan_access_dan_refresh_token(): void
    {
        $this->fakeOtpSender();
        $member = User::factory()->create(['email' => 'john@example.com']);

        $data = $this->loginTokensFor($member);

        $this->assertSame('Bearer', $data['token_type']);
        $this->assertSame(900, $data['expires_in']);
        $this->assertNotEmpty($data['access_token']);
        $this->assertNotEmpty($data['refresh_token']);

        $refresh = DB::table('refresh_tokens')->first();

        $this->assertNotNull($refresh);
        $this->assertSame(hash('sha256', $data['refresh_token']), $refresh->token_hash);
        $this->assertNotSame($data['refresh_token'], $refresh->token_hash);
        $this->assertNull($refresh->revoked_at);

        $claims = $this->decodeAccessToken($data['access_token']);

        $this->assertSame($member->user_id, (int) $claims->sub);
        $this->assertSame('member', $claims->role);
        $this->assertSame(900, $claims->exp - $claims->iat);
    }

    public function test_verify_otp_kode_salah_lima_kali_menolak_kode_benar(): void
    {
        $this->fakeOtpSender();
        User::factory()->create(['email' => 'john@example.com']);
        $this->requestOtpFor('john@example.com');

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->postJson('/api/v1/auth/verify-otp', ['email' => 'john@example.com', 'code' => '000000'])
                ->assertStatus(422)
                ->assertJsonPath('code', 'OTP_INVALID');
        }

        $this->assertSame(5, (int) DB::table('otp_requests')->value('attempts'));

        $this->postJson('/api/v1/auth/verify-otp', [
            'email' => 'john@example.com',
            'code' => $this->otpSender->lastCodeFor('john@example.com'),
        ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'OTP_INVALID');

        $this->assertSame(0, DB::table('refresh_tokens')->count());
    }

    public function test_verify_otp_kedaluwarsa_setelah_lima_menit(): void
    {
        $this->fakeOtpSender();
        User::factory()->create(['email' => 'john@example.com']);
        $this->requestOtpFor('john@example.com');

        $this->travel(6)->minutes();

        $this->postJson('/api/v1/auth/verify-otp', [
            'email' => 'john@example.com',
            'code' => $this->otpSender->lastCodeFor('john@example.com'),
        ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'OTP_EXPIRED');
    }

    public function test_verify_otp_email_belum_terdaftar_menolak(): void
    {
        $this->fakeOtpSender();

        $this->postJson('/api/v1/auth/verify-otp', ['email' => 'unknown@example.com', 'code' => '123456'])
            ->assertStatus(404)
            ->assertJsonPath('code', 'MEMBER_NOT_FOUND')
            ->assertJsonPath('status', 'error');
    }

    public function test_rate_limit_request_otp_per_email(): void
    {
        $this->fakeOtpSender();
        User::factory()->create(['email' => 'john@example.com']);

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->requestOtpFor('john@example.com');
        }

        $this->postJson('/api/v1/auth/request-otp', ['email' => 'john@example.com'])
            ->assertStatus(429)
            ->assertJsonPath('code', 'OTP_RATE_LIMITED')
            ->assertJsonStructure(['meta' => ['details' => ['retry_after_seconds']]]);
    }

    public function test_rate_limit_request_otp_per_ip(): void
    {
        $this->fakeOtpSender();

        for ($attempt = 1; $attempt <= 20; $attempt++) {
            $this->postJson('/api/v1/auth/request-otp', ['email' => sprintf('user%06d@example.com', $attempt)])
                ->assertOk();
        }

        $this->postJson('/api/v1/auth/request-otp', ['email' => '628100999999'])
            ->assertStatus(429)
            ->assertJsonPath('code', 'OTP_RATE_LIMITED');
    }

    private function decodeAccessToken(string $token): object
    {
        return JWT::decode(
            $token,
            new Key((string) file_get_contents(base_path('storage/app/keys/jwt-public.pem')), 'RS256'),
        );
    }
}
