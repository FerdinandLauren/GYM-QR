<?php

namespace Tests\Support;

use App\Support\OtpSender;

/**
 * Menangkap kode OTP di batas OtpSender supaya test bisa memverifikasi hash dan alur login.
 */
final class CapturingOtpSender implements OtpSender
{
    /**
     * @var array<string, list<string>>
     */
    public array $codes = [];

    public function send(string $phoneNumber, string $code): void
    {
        $this->codes[$phoneNumber][] = $code;
    }

    public function lastCodeFor(string $phoneNumber): string
    {
        $codes = $this->codes[$phoneNumber] ?? [];

        return $codes === [] ? '' : $codes[array_key_last($codes)];
    }

    public function countFor(string $phoneNumber): int
    {
        return count($this->codes[$phoneNumber] ?? []);
    }
}
