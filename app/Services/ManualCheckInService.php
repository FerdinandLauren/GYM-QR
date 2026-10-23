<?php

namespace App\Services;

use App\Exceptions\ApiException;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Check-in manual oleh kasir (FR-CAS-05, AT-11).
 * Tanpa koordinat; validasi membership tetap berlaku.
 */
final class ManualCheckInService
{
    public function __construct(
        private readonly MembershipResolver $memberships,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function checkIn(User $staff, array $data): array
    {
        $userId = (int) $data['user_id'];
        $key = (string) $data['idempotency_key'];

        $replayed = $this->findByKey($userId, $key);

        if ($replayed !== null) {
            return $this->payload($replayed);
        }

        $member = User::query()->where('role', 'member')->find($userId);

        if ($member === null) {
            throw ApiException::notFound('Member tidak ditemukan.');
        }

        try {
            return DB::transaction(function () use ($staff, $member, $data, $key): array {
                $existing = $this->findByKey($member->user_id, $key);

                if ($existing !== null) {
                    return $this->payload($existing);
                }

                DB::table('attendance_logs')
                    ->where('user_id', $member->user_id)
                    ->orderBy('log_id')
                    ->lockForUpdate()
                    ->get();

                $open = DB::table('attendance_logs')
                    ->where('user_id', $member->user_id)
                    ->whereNull('checkout_time')
                    ->orderByDesc('log_id')
                    ->first();

                if ($open !== null) {
                    $checkin = Carbon::parse((string) $open->checkin_time, 'UTC');

                    throw ApiException::sessionActive(
                        $checkin->toIso8601ZuluString(),
                        $checkin->copy()->setTimezone('Asia/Jakarta')->format('H:i'),
                        null,
                    );
                }

                $membership = DB::table('memberships')
                    ->where('membership_id', (int) $data['membership_id'])
                    ->where('user_id', $member->user_id)
                    ->first();

                if ($membership === null) {
                    throw ApiException::notFound('Membership tidak ditemukan untuk member ini.');
                }

                $status = $this->memberships->effectiveStatus($membership, now());

                if ($status !== 'active') {
                    throw in_array($status, ['suspended', 'cancelled'], true)
                        ? ApiException::membershipInactive()
                        : ApiException::membershipExpired();
                }

                if ($membership->type === 'single_visit') {
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

                $now = now();
                $logId = DB::table('attendance_logs')->insertGetId([
                    'user_id' => $member->user_id,
                    'membership_id' => (int) $membership->membership_id,
                    'locker_state' => ($data['locker_intent'] ?? 'no_locker') === 'need_locker' ? 'pending' : 'none',
                    'checkin_time' => $now,
                    'checkin_method' => 'manual_receptionist',
                    'manual_reason' => (string) $data['reason'],
                    'idempotency_key' => $key,
                ]);

                $this->audit->log($staff, 'attendance.manual_checkin', 'attendance_log', $logId, null, [
                    'user_id' => $member->user_id,
                    'membership_id' => (int) $membership->membership_id,
                    'reason' => (string) $data['reason'],
                    'note' => $data['note'] ?? null,
                ]);

                $row = DB::table('attendance_logs as a')
                    ->join('memberships as m', 'm.membership_id', '=', 'a.membership_id')
                    ->join('users as u', 'u.user_id', '=', 'a.user_id')
                    ->where('a.log_id', $logId)
                    ->select('a.*', 'm.type as membership_type', 'u.full_name')
                    ->first();

                if ($row === null) {
                    throw ApiException::notFound('Sesi kunjungan tidak ditemukan.');
                }

                return $this->payload($row);
            }, 3);
        } catch (QueryException $exception) {
            if (($exception->errorInfo[1] ?? null) === 1062) {
                $existing = $this->findByKey($userId, $key);

                if ($existing !== null) {
                    return $this->payload($existing);
                }
            }

            throw $exception;
        }
    }

    private function findByKey(int $userId, string $key): ?object
    {
        return DB::table('attendance_logs as a')
            ->join('memberships as m', 'm.membership_id', '=', 'a.membership_id')
            ->join('users as u', 'u.user_id', '=', 'a.user_id')
            ->where('a.user_id', $userId)
            ->where('a.idempotency_key', $key)
            ->select('a.*', 'm.type as membership_type', 'u.full_name')
            ->first();
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(object $row): array
    {
        return [
            'log_id' => (int) $row->log_id,
            'checkin_time' => Carbon::parse((string) $row->checkin_time, 'UTC')->toIso8601ZuluString(),
            'member_name' => (string) $row->full_name,
            'membership_type' => (string) $row->membership_type,
            'checkin_method' => 'manual_receptionist',
            'locker_state' => (string) $row->locker_state,
        ];
    }
}
