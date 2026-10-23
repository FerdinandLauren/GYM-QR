<?php

namespace Tests\Feature;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\Support\InteractsWithAuth;
use Tests\TestCase;

class MemberTokenTest extends TestCase
{
    use InteractsWithAuth, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware('auth:member')->get('/api/v1/_test/me', fn (): JsonResponse => response()->json([
            'status' => 'success',
            'data' => ['user_id' => auth('member')->id()],
        ]));
    }

    public function test_access_token_diterima_guard_member(): void
    {
        $this->fakeOtpSender();
        $tokens = $this->loginTokensFor($this->createMember());

        $this->withToken($tokens['access_token'])
            ->getJson('/api/v1/_test/me')
            ->assertOk()
            ->assertJsonPath('data.user_id', $this->memberId($tokens));
    }

    public function test_access_token_kedaluwarsa_menolak_dengan_token_expired(): void
    {
        config(['auth.jwt.access_ttl' => -60]);

        $this->fakeOtpSender();
        $tokens = $this->loginTokensFor($this->createMember());

        $this->withToken($tokens['access_token'])
            ->getJson('/api/v1/_test/me')
            ->assertStatus(401)
            ->assertJsonPath('code', 'TOKEN_EXPIRED');
    }

    public function test_signature_access_token_tidak_valid_ditolak(): void
    {
        $this->fakeOtpSender();
        $tokens = $this->loginTokensFor($this->createMember());

        $parts = explode('.', $tokens['access_token']);
        $parts[2] = strrev($parts[2]);

        $this->withToken(implode('.', $parts))
            ->getJson('/api/v1/_test/me')
            ->assertStatus(401)
            ->assertJsonPath('code', 'UNAUTHENTICATED');
    }

    public function test_alg_bukan_rs256_ditolak(): void
    {
        $publicKey = (string) file_get_contents(base_path('storage/app/keys/jwt-public.pem'));

        $token = JWT::encode([
            'sub' => 1,
            'role' => 'member',
            'iat' => time(),
            'exp' => time() + 900,
        ], $publicKey, 'HS256');

        $this->withToken($token)
            ->getJson('/api/v1/_test/me')
            ->assertStatus(401)
            ->assertJsonPath('code', 'UNAUTHENTICATED');
    }

    public function test_refresh_token_merotasi_dan_menonaktifkan_token_lama(): void
    {
        $this->fakeOtpSender();
        $tokens = $this->loginTokensFor($this->createMember());

        $rotated = $this->postJson('/api/v1/auth/refresh', ['refresh_token' => $tokens['refresh_token']])
            ->assertOk()
            ->json('data');

        $this->assertNotSame($tokens['refresh_token'], $rotated['refresh_token']);
        $this->assertNotEmpty($rotated['access_token']);

        $old = DB::table('refresh_tokens')->where('token_hash', hash('sha256', $tokens['refresh_token']))->first();
        $this->assertNotNull($old?->revoked_at);

        $this->postJson('/api/v1/auth/refresh', ['refresh_token' => $tokens['refresh_token']])
            ->assertStatus(401)
            ->assertJsonPath('code', 'UNAUTHENTICATED');

        $this->postJson('/api/v1/auth/refresh', ['refresh_token' => $rotated['refresh_token']])
            ->assertOk();
    }

    public function test_refresh_token_kedaluwarsa_ditolak(): void
    {
        config(['auth.jwt.refresh_ttl' => -60]);

        $this->fakeOtpSender();
        $tokens = $this->loginTokensFor($this->createMember());

        $this->postJson('/api/v1/auth/refresh', ['refresh_token' => $tokens['refresh_token']])
            ->assertStatus(401)
            ->assertJsonPath('code', 'UNAUTHENTICATED');
    }

    public function test_logout_mencabut_refresh_token_aktif(): void
    {
        $this->fakeOtpSender();
        $tokens = $this->loginTokensFor($this->createMember());

        $this->withToken($tokens['access_token'])
            ->postJson('/api/v1/auth/logout')
            ->assertOk()
            ->assertJsonPath('status', 'success');

        $this->assertSame(
            0,
            DB::table('refresh_tokens')->whereNull('revoked_at')->count(),
        );

        $this->postJson('/api/v1/auth/refresh', ['refresh_token' => $tokens['refresh_token']])
            ->assertStatus(401);
    }

    public function test_logout_idempoten_saat_diulang(): void
    {
        $this->fakeOtpSender();
        $tokens = $this->loginTokensFor($this->createMember());

        $this->withToken($tokens['access_token'])->postJson('/api/v1/auth/logout')->assertOk();
        $this->withToken($tokens['access_token'])->postJson('/api/v1/auth/logout')->assertOk();
    }

    public function test_logout_tanpa_access_token_ditolak(): void
    {
        $this->postJson('/api/v1/auth/logout')
            ->assertStatus(401)
            ->assertJsonPath('code', 'UNAUTHENTICATED');
    }

    /**
     * @param  array<string, mixed>  $tokens
     */
    private function memberId(array $tokens): int
    {
        $claims = $this->decodeAccessToken((string) $tokens['access_token']);

        return (int) $claims->sub;
    }

    private function decodeAccessToken(string $token): object
    {
        return JWT::decode($token, new Key((string) file_get_contents(base_path('storage/app/keys/jwt-public.pem')), 'RS256'));
    }
}
