<?php

namespace Tests\Support;

use App\Support\OtpSender;

/**
 * Test double: menyimpan kode terakhir per email tanpa mengirim apa pun.
 */
final class CapturingOtpSender implements OtpSender
{
    /** @var array<string, string> */
    private array $codes = [];

    /** @var array<string, int> */
    private array $counts = [];

    public function send(string $email, string $code): void
    {
        $this->codes[$email] = $code;
        $this->counts[$email] = ($this->counts[$email] ?? 0) + 1;
    }

    public function lastCodeFor(string $email): string
    {
        return $this->codes[$email] ?? '';
    }

    public function countFor(string $email): int
    {
        return $this->counts[$email] ?? 0;
    }
}
