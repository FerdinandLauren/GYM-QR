<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class RateLimiterTest extends TestCase
{
    public function test_semua_limiter_terdaftar(): void
    {
        foreach (['otp-request', 'otp-verify', 'scan-qr', 'assign-locker', 'manual-checkin', 'staff-login'] as $name) {
            $this->assertNotNull(RateLimiter::limiter($name), "Limiter {$name} belum terdaftar.");
        }
    }

    public function test_limiter_otp_request_mengembalikan_otp_rate_limited(): void
    {
        Route::middleware('throttle:otp-request')
            ->post('/api/v1/uji/otp', fn () => response()->json(['status' => 'success']));

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->postJson('/api/v1/uji/otp', ['phone_number' => '628123456789'])->assertOk();
        }

        $this->postJson('/api/v1/uji/otp', ['phone_number' => '628123456789'])
            ->assertStatus(429)
            ->assertJsonPath('status', 'error')
            ->assertJsonPath('code', 'OTP_RATE_LIMITED')
            ->assertJsonStructure(['meta' => ['details' => ['retry_after_seconds']]]);
    }

    public function test_limiter_scan_qr_mengembalikan_rate_limited(): void
    {
        Route::middleware('throttle:scan-qr')
            ->get('/api/v1/uji/scan', fn () => response()->json(['status' => 'success']));

        for ($attempt = 1; $attempt <= 10; $attempt++) {
            $this->getJson('/api/v1/uji/scan')->assertOk();
        }

        $this->getJson('/api/v1/uji/scan')
            ->assertStatus(429)
            ->assertJsonPath('code', 'RATE_LIMITED')
            ->assertHeader('Retry-After');
    }
}
