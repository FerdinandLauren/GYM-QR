<?php

namespace App\Support;

/**
 * Batas pengiriman OTP. Kanal: email (keputusan owner, menggantikan WhatsApp).
 */
interface OtpSender
{
    public function send(string $email, string $code): void;
}
