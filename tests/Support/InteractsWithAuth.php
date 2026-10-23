<?php

namespace Tests\Support;

use App\Models\User;
use App\Support\OtpSender;

trait InteractsWithAuth
{
    protected CapturingOtpSender $otpSender;

    protected function fakeOtpSender(): CapturingOtpSender
    {
        $this->otpSender = new CapturingOtpSender;
        $this->app->instance(OtpSender::class, $this->otpSender);

        return $this->otpSender;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function createMember(array $attributes = []): User
    {
        return User::factory()->create($attributes + ['role' => 'member']);
    }

    protected function requestOtpFor(string $email): void
    {
        $this->postJson('/api/v1/auth/request-otp', ['email' => $email])->assertOk();
    }

    /**
     * @return array<string, mixed>
     */
    protected function loginTokensFor(User $member): array
    {
        $this->requestOtpFor((string) $member->email);
        $code = $this->otpSender->lastCodeFor((string) $member->email);

        return $this->postJson('/api/v1/auth/verify-otp', [
            'email' => $member->email,
            'code' => $code,
        ])->assertOk()->json('data');
    }
}
