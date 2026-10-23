<?php

namespace App\Services;

use App\Exceptions\ApiException;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Assign, return, tandai tanpa loker, verifikasi identitas, daftar sesi & loker
 * (FR-CAS-02..07, PRD §6.2).
 */
final class LockerService
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly MembershipResolver $memberships,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function assign(User $staff, int $logId, int $lockerId): array
    {
        return DB::transaction(function () use ($staff, $logId, $lockerId): array {
            // Kunci baris loker lebih dulu agar AT-08 deterministik: penantang melihat
            // status 'occupied' dan menerima LOCKER_TAKEN, bukan LOCKER_ALREADY_ASSIGNED.
            $locker = DB::table('lockers')->where('locker_id', $lockerId)->lockForUpdate()->first();

            if ($locker === null) {
                throw ApiException::notFound('Loker tidak ditemukan.');
            }

            if ($locker->status !== 'available') {
                throw ApiException::lockerTaken();
            }

            $log = $this->lockedLog($logId);

            if ($log->checkout_time !== null) {
                throw ApiException::sessionClosed();
            }

            if ($log->locker_state === 'assigned') {
                throw ApiException::lockerAlreadyAssigned();
            }

            // PRD §6.2: update bersyarat; affected_rows 0 berarti kasir lain menang balapan.
            $affected = DB::table('lockers')
                ->where('locker_id', $lockerId)
                ->where('status', 'available')
                ->update(['status' => 'occupied']);

            if ($affected === 0) {
                throw ApiException::lockerTaken();
            }

            $now = now();

            DB::table('attendance_logs')->where('log_id', $logId)->update([
                'locker_id' => $lockerId,
                'locker_state' => 'assigned',
                'assigned_by_staff_id' => $staff->user_id,
                'assigned_at' => $now,
            ]);

            $this->audit->log($staff, 'locker.assigned', 'attendance_log', $logId, null, [
                'locker_id' => $lockerId,
                'locker_number' => $locker->locker_number,
            ]);

            return [
                'log_id' => $logId,
                'locker_id' => $lockerId,
                'locker_number' => (string) $locker->locker_number,
                'locker_state' => 'assigned',
                'assigned_at' => Carbon::parse((string) $now, 'UTC')->toIso8601ZuluString(),
                'member_name' => $this->memberName((int) $log->user_id),
            ];
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function release(User $staff, int $logId): array
    {
        return DB::transaction(function () use ($staff, $logId): array {
            $log = $this->lockedLog($logId);
            $lockerNumber = $this->lockerNumber($log->locker_id);

            if ($log->locker_state === 'returned' && $log->checkout_type === 'locker_returned') {
                return [
                    'log_id' => $logId,
                    'locker_number' => $lockerNumber,
                    'checkout_time' => $this->iso($log->checkout_time),
                    'already_returned' => true,
                ];
            }

            if ($log->checkout_time !== null) {
                throw ApiException::sessionClosed();
            }

            if ($log->locker_id === null) {
                throw ApiException::validationError('Sesi ini tidak memakai loker.');
            }

            $now = now();

            DB::table('attendance_logs')->where('log_id', $logId)->update([
                'checkout_time' => $now,
                'checkout_type' => 'locker_returned',
                'locker_state' => 'returned',
                'locker_returned_time' => $now,
            ]);

            DB::table('lockers')
                ->where('locker_id', (int) $log->locker_id)
                ->update(['status' => 'available']);

            $this->audit->log($staff, 'locker.returned', 'attendance_log', $logId, null, [
                'locker_id' => (int) $log->locker_id,
                'locker_number' => $lockerNumber,
            ]);

            return [
                'log_id' => $logId,
                'locker_number' => $lockerNumber,
                'checkout_time' => Carbon::parse((string) $now, 'UTC')->toIso8601ZuluString(),
                'already_returned' => false,
            ];
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function markNoLocker(User $staff, int $logId): array
    {
        return DB::transaction(function () use ($staff, $logId): array {
            $log = $this->lockedLog($logId);

            if ($log->checkout_time !== null) {
                throw ApiException::sessionClosed();
            }

            if ($log->locker_state === 'assigned') {
                throw ApiException::lockerAlreadyAssigned();
            }

            if ($log->locker_state !== 'none') {
                DB::table('attendance_logs')->where('log_id', $logId)->update([
                    'locker_state' => 'none',
                    'assigned_by_staff_id' => $staff->user_id,
                ]);

                $this->audit->log($staff, 'attendance.no_locker', 'attendance_log', $logId, null, [
                    'previous_state' => (string) $log->locker_state,
                ]);
            }

            return [
                'log_id' => $logId,
                'locker_state' => 'none',
            ];
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function verifyIdentity(User $staff, int $logId): array
    {
        return DB::transaction(function () use ($staff, $logId): array {
            $log = $this->lockedLog($logId);

            if ($log->identity_verified_at !== null) {
                return [
                    'log_id' => $logId,
                    'identity_verified_at' => $this->iso($log->identity_verified_at),
                    'already_verified' => true,
                ];
            }

            $now = now();

            DB::table('attendance_logs')->where('log_id', $logId)->update([
                'identity_verified_by' => $staff->user_id,
                'identity_verified_at' => $now,
            ]);

            $this->audit->log($staff, 'attendance.identity_verified', 'attendance_log', $logId);

            return [
                'log_id' => $logId,
                'identity_verified_at' => Carbon::parse((string) $now, 'UTC')->toIso8601ZuluString(),
                'already_verified' => false,
            ];
        });
    }

    /**
     * @return array{lockers: array<int, array<string, mixed>>, summary: array<string, int>}
     */
    public function lockers(): array
    {
        $rows = DB::table('lockers as l')
            ->leftJoin('attendance_logs as a', function ($join): void {
                $join->on('a.locker_id', '=', 'l.locker_id')->whereNull('a.checkout_time');
            })
            ->leftJoin('users as u', 'u.user_id', '=', 'a.user_id')
            ->orderBy('l.locker_number')
            ->select('l.locker_id', 'l.locker_number', 'l.status', 'a.log_id', 'a.checkin_time', 'u.full_name')
            ->get();

        $lockers = $rows->map(fn (object $row): array => [
            'locker_id' => (int) $row->locker_id,
            'locker_number' => (string) $row->locker_number,
            'status' => (string) $row->status,
            'occupant' => $row->log_id === null ? null : [
                'log_id' => (int) $row->log_id,
                'full_name' => (string) $row->full_name,
                'checkin_time' => $this->iso($row->checkin_time),
            ],
        ])->all();

        $summary = ['available' => 0, 'occupied' => 0, 'out_of_service' => 0];

        foreach ($rows as $row) {
            $summary[(string) $row->status]++;
        }

        return ['lockers' => $lockers, 'summary' => $summary];
    }

    /**
     * @param  array{date: string, filter: string, updated_since: string|null}  $filters
     * @return array<int, array<string, mixed>>
     */
    public function sessions(array $filters): array
    {
        $start = Carbon::parse($filters['date'], 'Asia/Jakarta')->startOfDay()->utc();
        $end = Carbon::parse($filters['date'], 'Asia/Jakarta')->endOfDay()->utc();

        $query = DB::table('attendance_logs as a')
            ->join('users as u', 'u.user_id', '=', 'a.user_id')
            ->join('memberships as m', 'm.membership_id', '=', 'a.membership_id')
            ->leftJoin('lockers as l', 'l.locker_id', '=', 'a.locker_id')
            ->whereBetween('a.checkin_time', [$start, $end])
            ->orderByDesc('a.checkin_time')
            ->select(
                'a.log_id',
                'a.checkin_time',
                'a.checkout_time',
                'a.locker_state',
                'a.identity_verified_at',
                'u.user_id',
                'u.full_name',
                'u.photo_url',
                'm.type as membership_type',
                'm.type as type',
                'm.status as status',
                'm.end_date',
                'm.remaining_visits',
                'l.locker_number',
            );

        if ($filters['updated_since'] !== null) {
            $updated = Carbon::parse($filters['updated_since'], 'UTC');

            $query->where(function ($inner) use ($updated): void {
                $inner->where('a.checkin_time', '>=', $updated)
                    ->orWhere('a.assigned_at', '>=', $updated)
                    ->orWhere('a.checkout_time', '>=', $updated)
                    ->orWhere('a.identity_verified_at', '>=', $updated);
            });
        }

        match ($filters['filter']) {
            'need_locker' => $query->where('a.locker_state', 'pending'),
            'assigned' => $query->where('a.locker_state', 'assigned'),
            'no_locker' => $query->where('a.locker_state', 'none'),
            'unverified' => $query->whereNull('a.identity_verified_at'),
            default => null,
        };

        return $query->get()->map(fn (object $row): array => [
            'log_id' => (int) $row->log_id,
            'member' => [
                'user_id' => (int) $row->user_id,
                'full_name' => (string) $row->full_name,
                'photo_url' => $row->photo_url === null ? null : (string) $row->photo_url,
            ],
            'membership_type' => (string) $row->membership_type,
            'effective_status' => $this->memberships->effectiveStatus($row, now()),
            'checkin_time' => $this->iso($row->checkin_time),
            'checkout_time' => $this->iso($row->checkout_time),
            'locker_state' => (string) $row->locker_state,
            'locker_number' => $row->locker_number === null ? null : (string) $row->locker_number,
            'identity_verified_at' => $this->iso($row->identity_verified_at),
        ])->all();
    }

    private function lockedLog(int $logId): object
    {
        $log = DB::table('attendance_logs')->where('log_id', $logId)->lockForUpdate()->first();

        if ($log === null) {
            throw ApiException::notFound('Sesi kunjungan tidak ditemukan.');
        }

        return $log;
    }

    private function memberName(int $userId): string
    {
        return (string) DB::table('users')->where('user_id', $userId)->value('full_name');
    }

    private function lockerNumber(mixed $lockerId): ?string
    {
        if ($lockerId === null) {
            return null;
        }

        $number = DB::table('lockers')->where('locker_id', (int) $lockerId)->value('locker_number');

        return $number === null ? null : (string) $number;
    }

    private function iso(mixed $value): ?string
    {
        return $value === null ? null : Carbon::parse((string) $value, 'UTC')->toIso8601ZuluString();
    }
}
