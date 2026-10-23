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

    protected function requestOtpFor(string $phoneNumber): void
    {
        $this->postJson('/api/v1/auth/request-otp', ['phone_number' => $phoneNumber])->assertOk();
    }

    /**
     * @return array<string, mixed>
     */
    protected function loginTokensFor(User $member): array
    {
        $this->requestOtpFor($member->phone_number);
        $code = $this->otpSender->lastCodeFor($member->phone_number);

        return $this->postJson('/api/v1/auth/verify-otp', [
            'phone_number' => $member->phone_number,
            'code' => $code,
        ])->assertOk()->json('data');
    }
}
