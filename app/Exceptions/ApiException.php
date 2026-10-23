<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Error domain yang sudah dipetakan ke kode PRD §7.3.
 */
final class ApiException extends RuntimeException
{
    /**
     * @param  array<string, mixed>|null  $details
     */
    private function __construct(
        string $message,
        public readonly int $status,
        public readonly string $errorCode,
        public readonly ?array $details = null,
    ) {
        parent::__construct($message);
    }

    public static function unauthenticated(string $message = 'Sesi Anda berakhir. Silakan login kembali.'): self
    {
        return new self($message, 401, 'UNAUTHENTICATED');
    }

    public static function tokenExpired(): self
    {
        return new self('Sesi Anda berakhir. Silakan login kembali.', 401, 'TOKEN_EXPIRED');
    }

    public static function memberNotFound(): self
    {
        return new self('Nomor belum terdaftar, silakan daftar di meja kasir.', 404, 'MEMBER_NOT_FOUND');
    }

    public static function otpInvalid(): self
    {
        return new self('Kode OTP salah. Coba lagi.', 422, 'OTP_INVALID');
    }

    public static function otpExpired(): self
    {
        return new self('Kode OTP kedaluwarsa. Minta kode baru.', 422, 'OTP_EXPIRED');
    }
}
