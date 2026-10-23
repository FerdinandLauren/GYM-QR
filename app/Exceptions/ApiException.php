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

    public static function invalidQr(): self
    {
        return new self('QR tidak dikenali. Pastikan memindai QR di meja kasir.', 400, 'INVALID_QR');
    }

    public static function gpsAccuracyLow(): self
    {
        return new self('Sinyal GPS lemah. Minta check-in manual ke kasir.', 422, 'GPS_ACCURACY_LOW');
    }

    public static function geoOutOfRange(int $radiusMeters, float $distanceMeters, float $accuracyMeters): self
    {
        return new self(
            "Lokasi Anda terlalu jauh dari lokasi Gym (maksimal {$radiusMeters} meter).",
            403,
            'GEO_OUT_OF_RANGE',
            [
                'distance_m' => $distanceMeters,
                'radius_m' => $radiusMeters,
                'accuracy_m' => $accuracyMeters,
            ],
        );
    }

    public static function sessionActive(string $checkinTimeIso, string $checkinTimeLocal, ?string $lockerNumber): self
    {
        $locker = $lockerNumber === null ? 'tanpa loker' : "#{$lockerNumber}";

        return new self(
            "Anda sudah check-in pukul {$checkinTimeLocal}. Loker: {$locker}.",
            409,
            'SESSION_ACTIVE',
            [
                'checkin_time' => $checkinTimeIso,
                'locker_number' => $lockerNumber,
            ],
        );
    }

    public static function rescanGuard(int $retryAfterSeconds): self
    {
        $minutes = max(1, (int) ceil($retryAfterSeconds / 60));

        return new self(
            "Baru saja check-in. Coba lagi dalam {$minutes} menit.",
            429,
            'RESCAN_GUARD',
            ['retry_after_seconds' => $retryAfterSeconds],
        );
    }

    public static function membershipExpired(): self
    {
        return new self('Paket Anda sudah berakhir. Perpanjang di meja kasir.', 403, 'MEMBERSHIP_EXPIRED');
    }

    public static function membershipInactive(): self
    {
        return new self('Paket Anda sedang tidak aktif. Hubungi kasir.', 403, 'MEMBERSHIP_INACTIVE');
    }

    public static function serviceUnavailable(): self
    {
        return new self('Layanan check-in sedang tidak tersedia. Silakan check-in manual ke kasir.', 503, 'SERVICE_UNAVAILABLE');
    }
}
