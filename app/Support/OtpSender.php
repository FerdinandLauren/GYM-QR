<?php

namespace App\Support;

/**
 * Batas pengiriman OTP. Implementasi nyata menunggu keputusan Q2 (penyedia WhatsApp).
 */
interface OtpSender
{
    public function send(string $phoneNumber, string $code): void;
}
