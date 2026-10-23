<?php

namespace App\Support;

use Illuminate\Support\Facades\Mail;

/**
 * Mengirim kode OTP lewat email. Di lokal, MAIL_MAILER=log menulis ke log
 * tanpa kode OTP (kode tidak pernah dicatat, AGENTS §6).
 */
final class EmailOtpSender implements OtpSender
{
    public function send(string $email, string $code): void
    {
        Mail::raw(
            "Kode OTP GymPass QR Anda: {$code}. Berlaku 5 menit. Jangan bagikan kode ini.",
            fn ($message) => $message->to($email)->subject('Kode OTP GymPass QR'),
        );
    }
}
