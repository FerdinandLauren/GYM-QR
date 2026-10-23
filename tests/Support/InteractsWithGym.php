<?php

namespace Tests\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

/**
 * Fixture data gym untuk alur check-in (PRD §6.1).
 */
trait InteractsWithGym
{
    protected const QR_PAYLOAD = 'GYM_STATIC_TOKEN_XYZ123';

    /** @var array<int, string> */
    private array $accessTokens = [];

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function createGymSettings(array $overrides = []): void
    {
        DB::table('gym_settings')->insert($overrides + [
            'gym_id' => 1,
            'gym_name' => 'GymPass Test',
            'timezone' => 'Asia/Jakarta',
            'latitude' => 0,
            'longitude' => 0,
            'geofence_radius_m' => 30,
            'geofence_accuracy_max_m' => 150,
            'rescan_guard_seconds' => 300,
            'closing_time' => '23:00:00',
            'qr_payload' => self::QR_PAYLOAD,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function createPackage(array $overrides = []): int
    {
        return DB::table('packages')->insertGetId($overrides + [
            'code' => 'PKG-'.Str::upper(Str::random(8)),
            'name' => 'Bulanan Unlimited',
            'type' => 'monthly_unlimited',
            'duration_days' => 30,
            'visit_quota' => null,
            'price' => 250000,
            'is_active' => true,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function createMembership(User $member, array $overrides = []): int
    {
        $type = (string) ($overrides['type'] ?? 'monthly_unlimited');
        $today = now()->timezone('Asia/Jakarta')->toDateString();

        $packageId = $overrides['package_id'] ?? $this->createPackage([
            'name' => $type === 'single_visit' ? 'Day Pass' : 'Bulanan Unlimited',
            'type' => $type,
            'duration_days' => $type === 'single_visit' ? 1 : 30,
            'visit_quota' => $type === 'single_visit' ? 1 : null,
        ]);

        unset($overrides['package_id']);

        return DB::table('memberships')->insertGetId($overrides + [
            'user_id' => $member->user_id,
            'package_id' => $packageId,
            'type' => $type,
            'remaining_visits' => $type === 'single_visit' ? 1 : null,
            'price_snapshot' => 0,
            'start_date' => $today,
            'end_date' => $type === 'single_visit'
                ? $today
                : now()->timezone('Asia/Jakarta')->addDays(30)->toDateString(),
            'status' => 'active',
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function createAttendance(User $member, int $membershipId, array $overrides = []): int
    {
        return DB::table('attendance_logs')->insertGetId($overrides + [
            'user_id' => $member->user_id,
            'membership_id' => $membershipId,
            'checkin_time' => now()->subHour(),
            'checkout_time' => now()->subMinutes(30),
            'locker_state' => 'none',
            'checkin_method' => 'qr_scan',
            'latitude' => 0,
            'longitude' => 0,
            'gps_accuracy_m' => 10,
        ]);
    }

    protected function createLocker(string $number): int
    {
        return DB::table('lockers')->insertGetId([
            'locker_number' => $number,
            'status' => 'occupied',
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function scanPayload(array $overrides = []): array
    {
        return $overrides + [
            'qr_payload' => self::QR_PAYLOAD,
            'latitude' => 0,
            'longitude' => 0,
            'accuracy' => 10,
            'locker_intent' => 'no_locker',
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function postScan(User $member, array $payload, string $idempotencyKey): TestResponse
    {
        return $this->withToken($this->tokenFor($member))->postJson(
            '/api/v1/attendance/scan-qr',
            $payload,
            ['Idempotency-Key' => $idempotencyKey],
        );
    }

    protected function tokenFor(User $member): string
    {
        if (! isset($this->accessTokens[$member->user_id])) {
            if (! isset($this->otpSender)) {
                $this->fakeOtpSender();
            }

            $this->accessTokens[$member->user_id] = $this->loginTokensFor($member)['access_token'];
        }

        return $this->accessTokens[$member->user_id];
    }

    /**
     * Jarak meter pada lingkaran bujur ke ekuator, dipakai untuk menyusun koordinat uji.
     */
    protected function latitudeOffset(float $meters): float
    {
        return rad2deg($meters / 6371000.0);
    }
}
