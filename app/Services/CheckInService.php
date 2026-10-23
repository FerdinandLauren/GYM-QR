<?php

namespace App\Services;

use App\Exceptions\ApiException;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Check-in QR member (FR-MEM-02..04, FR-MEM-05, PRD §6.2).
 */
final class CheckInService
{
    public function __construct(
        private readonly MembershipResolver $memberships,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function scan(User $member, array $data): array
    {
        $key = (string) $data['idempotency_key'];

        // PRD §5.2 invariant 4: key yang sama mengembalikan respons tersimpan tanpa validasi ulang.
        $replayed = $this->findByIdempotency($member->user_id, $key);

        if ($replayed !== null) {
            return $this->payload($member, $replayed);
        }

        $settings = DB::table('gym_settings')->first();

        if ($settings === null) {
            throw ApiException::serviceUnavailable();
        }

        $this->validateQr($member, $settings, (string) $data['qr_payload']);
        $this->validateGeofence(
            $member,
            $settings,
            (float) $data['latitude'],
            (float) $data['longitude'],
            (float) $data['accuracy'],
        );

        $lockerIntent = (string) ($data['locker_intent'] ?? 'no_locker');

        return $this->withCheckinLock($member->user_id, function () use ($member, $data, $key, $lockerIntent, $settings): array {
            try {
                return DB::transaction(function () use ($member, $data, $key, $lockerIntent, $settings): array {
                    $existing = $this->findByIdempotency($member->user_id, $key);

                    if ($existing !== null) {
                        return $this->payload($member, $existing);
                    }

                    // PRD §6.2: kunci baris absensi user sebelum memeriksa sesi terbuka.
                    DB::table('attendance_logs')
                        ->where('user_id', $member->user_id)
                        ->orderBy('log_id')
                        ->lockForUpdate()
                        ->get();

                    $open = $this->openSession($member->user_id);

                    if ($open !== null) {
                        throw $this->sessionActive($open);
                    }

                    $last = DB::table('attendance_logs')
                        ->where('user_id', $member->user_id)
                        ->orderByDesc('checkin_time')
                        ->orderByDesc('log_id')
                        ->first();

                    if ($last !== null) {
                        $anchor = Carbon::parse((string) ($last->checkout_time ?? $last->checkin_time), 'UTC');
                        $allowedAt = $anchor->copy()->addSeconds((int) $settings->rescan_guard_seconds);
                        $retryAfter = $allowedAt->getTimestamp() - now()->getTimestamp();

                        if ($retryAfter > 0) {
                            throw ApiException::rescanGuard($retryAfter);
                        }
                    }

                    ['membership' => $membership, 'reason' => $reason] = $this->memberships->forCheckIn($member->user_id, now());

                    if ($membership === null) {
                        throw $reason === 'MEMBERSHIP_INACTIVE'
                            ? ApiException::membershipInactive()
                            : ApiException::membershipExpired();
                    }

                    if ($membership->type === 'single_visit') {
                        $this->consumeDayPass($membership);
                    }

                    $logId = DB::table('attendance_logs')->insertGetId([
                        'user_id' => $member->user_id,
                        'membership_id' => (int) $membership->membership_id,
                        'locker_state' => $lockerIntent === 'need_locker' ? 'pending' : 'none',
                        'checkin_time' => now(),
                        'checkin_method' => 'qr_scan',
                        'latitude' => (float) $data['latitude'],
                        'longitude' => (float) $data['longitude'],
                        'gps_accuracy_m' => (float) $data['accuracy'],
                        'idempotency_key' => $key,
                    ]);

                    return $this->payload($member, $this->rowById($member->user_id, $logId));
                }, 3);
            } catch (ApiException $exception) {
                if (in_array($exception->errorCode, ['MEMBERSHIP_EXPIRED', 'MEMBERSHIP_INACTIVE'], true)) {
                    $this->audit->log($member, 'attendance.scan_rejected', 'user', $member->user_id, null, [
                        'reason_code' => $exception->errorCode,
                    ]);
                }

                throw $exception;
            } catch (QueryException $exception) {
                // Balapan dua request dengan key sama: transaksi sudah rollback, kembalikan baris pemenang.
                if ($this->isDuplicateKey($exception)) {
                    $existing = $this->findByIdempotency($member->user_id, $key);

                    if ($existing !== null) {
                        return $this->payload($member, $existing);
                    }
                }

                throw $exception;
            }
        });
    }

    private function validateQr(User $member, object $settings, string $payload): void
    {
        // Perbandingan constant-time (FR-MEM-02); payload lama otomatis invalid setelah regenerasi.
        if (! hash_equals((string) $settings->qr_payload, $payload)) {
            $this->audit->log($member, 'attendance.scan_rejected', 'user', $member->user_id, null, [
                'reason_code' => 'INVALID_QR',
            ]);

            throw ApiException::invalidQr();
        }
    }

    private function validateGeofence(User $member, object $settings, float $latitude, float $longitude, float $accuracy): void
    {
        $maxAccuracy = (float) $settings->geofence_accuracy_max_m;

        if ($accuracy > $maxAccuracy) {
            $this->audit->log($member, 'attendance.scan_rejected', 'user', $member->user_id, null, [
                'reason_code' => 'GPS_ACCURACY_LOW',
                'accuracy_m' => $accuracy,
                'accuracy_max_m' => $maxAccuracy,
            ]);

            throw ApiException::gpsAccuracyLow();
        }

        // Pembulatan sentimeter menjaga batas radius + accuracy stabil (PRD §8.1).
        $distance = round($this->distanceMeters(
            (float) $settings->latitude,
            (float) $settings->longitude,
            $latitude,
            $longitude,
        ), 2);
        $radius = (int) $settings->geofence_radius_m;

        if ($distance > $radius + $accuracy) {
            $this->audit->log($member, 'attendance.scan_rejected', 'user', $member->user_id, null, [
                'reason_code' => 'GEO_OUT_OF_RANGE',
                'distance_m' => $distance,
                'radius_m' => $radius,
                'accuracy_m' => $accuracy,
            ]);

            throw ApiException::geoOutOfRange($radius, $distance, $accuracy);
        }
    }

    private function consumeDayPass(object $membership): void
    {
        $affected = DB::table('memberships')
            ->where('membership_id', (int) $membership->membership_id)
            ->where('remaining_visits', '>', 0)
            ->decrement('remaining_visits');

        if ($affected === 0) {
            throw ApiException::membershipExpired();
        }

        DB::table('memberships')
            ->where('membership_id', (int) $membership->membership_id)
            ->where('remaining_visits', 0)
            ->update(['status' => 'expired']);
    }

    private function sessionActive(object $openSession): ApiException
    {
        $checkin = Carbon::parse((string) $openSession->checkin_time, 'UTC');

        return ApiException::sessionActive(
            $checkin->toIso8601ZuluString(),
            $checkin->copy()->setTimezone('Asia/Jakarta')->format('H:i'),
            $openSession->locker_number === null ? null : (string) $openSession->locker_number,
        );
    }

    private function openSession(int $userId): ?object
    {
        return DB::table('attendance_logs as a')
            ->leftJoin('lockers as l', 'l.locker_id', '=', 'a.locker_id')
            ->where('a.user_id', $userId)
            ->whereNull('a.checkout_time')
            ->orderByDesc('a.log_id')
            ->select('a.log_id', 'a.checkin_time', 'l.locker_number')
            ->first();
    }

    private function findByIdempotency(int $userId, string $key): ?object
    {
        // Indeks unik tidak mengenal batas 10 menit; replay selalu mengembalikan baris yang sama.
        return $this->baseLogQuery($userId)
            ->where('a.idempotency_key', $key)
            ->first();
    }

    private function rowById(int $userId, int $logId): object
    {
        $row = $this->baseLogQuery($userId)->where('a.log_id', $logId)->first();

        if ($row === null) {
            throw new RuntimeException('Baris absensi tidak ditemukan setelah insert.');
        }

        return $row;
    }

    private function baseLogQuery(int $userId): Builder
    {
        return DB::table('attendance_logs as a')
            ->join('memberships as m', 'm.membership_id', '=', 'a.membership_id')
            ->leftJoin('lockers as l', 'l.locker_id', '=', 'a.locker_id')
            ->where('a.user_id', $userId)
            ->select('a.*', 'm.type as membership_type', 'l.locker_number');
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(User $member, object $row): array
    {
        return [
            'log_id' => (int) $row->log_id,
            'checkin_time' => Carbon::parse((string) $row->checkin_time, 'UTC')->toIso8601ZuluString(),
            'member_name' => $member->full_name,
            'membership_type' => (string) $row->membership_type,
            'locker_state' => (string) $row->locker_state,
            'locker_number' => $row->locker_number === null ? null : (string) $row->locker_number,
        ];
    }

    private function isDuplicateKey(QueryException $exception): bool
    {
        return ($exception->errorInfo[1] ?? null) === 1062;
    }

    private function distanceMeters(float $latFrom, float $lngFrom, float $latTo, float $lngTo): float
    {
        $earthRadius = 6371000.0;
        $deltaLat = deg2rad($latTo - $latFrom);
        $deltaLng = deg2rad($lngTo - $lngFrom);
        $a = sin($deltaLat / 2) ** 2
            + cos(deg2rad($latFrom)) * cos(deg2rad($latTo)) * sin($deltaLng / 2) ** 2;

        return $earthRadius * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    private function withCheckinLock(int $userId, callable $callback): mixed
    {
        $lock = null;

        try {
            $store = Cache::store('redis')->getStore();

            if (! $store instanceof LockProvider) {
                throw new RuntimeException('Cache store redis tidak mendukung lock.');
            }

            $lock = $store->lock("checkin:{$userId}", 10);
            $lock->block(3);
        } catch (Throwable $exception) {
            // PRD §8.7: Redis mati tidak boleh memblokir check-in; lock baris DB tetap menjaga.
            Log::warning('Lock Redis check-in tidak tersedia; melanjutkan dengan lock baris database.', [
                'exception' => $exception->getMessage(),
            ]);
            $lock = null;
        }

        try {
            return $callback();
        } finally {
            $lock?->release();
        }
    }
}
