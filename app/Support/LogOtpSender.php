<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;

/**
 * Pengirim OTP untuk pengembangan lokal: tidak mengirim apa pun dan tidak pernah
 * menulis kode OTP ke log (AGENTS §6).
 */
final class LogOtpSender implements OtpSender
{
    public function send(string $phoneNumber, string $code): void
    {
        Log::info('OTP dikirim lewat log driver.', [
            'phone_number' => $this->mask($phoneNumber),
        ]);
    }

    private function mask(string $phoneNumber): string
    {
        return substr($phoneNumber, 0, 4).'****'.substr($phoneNumber, -4);
    }
}
