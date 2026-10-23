<?php

namespace App\Services;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Pemilihan membership saat check-in dan effective_status (FR-MEM-05, PRD §4.3).
 * Kolom status hanya hasil job; keputusan selalu dihitung saat request.
 */
final class MembershipResolver
{
    private const INACTIVE_STATUSES = ['cancelled', 'suspended'];

    /**
     * @return array{membership: object|null, reason: string|null}
     */
    public function forCheckIn(int $userId, Carbon $now): array
    {
        $candidates = $this->dateValid($userId, $now);

        foreach ($candidates as $membership) {
            if ($this->effectiveStatus($membership, $now) === 'active') {
                return ['membership' => $membership, 'reason' => null];
            }
        }

        foreach ($candidates as $membership) {
            if (in_array($membership->status, self::INACTIVE_STATUSES, true)) {
                return ['membership' => null, 'reason' => 'MEMBERSHIP_INACTIVE'];
            }
        }

        return ['membership' => null, 'reason' => 'MEMBERSHIP_EXPIRED'];
    }

    public function forProfile(int $userId, Carbon $now): ?object
    {
        $candidates = $this->dateValid($userId, $now);

        foreach ($candidates as $membership) {
            if ($this->effectiveStatus($membership, $now) === 'active') {
                return $membership;
            }
        }

        if ($candidates !== []) {
            return $candidates[0];
        }

        return $this->baseQuery($userId)
            ->orderByRaw('m.end_date IS NULL')
            ->orderByDesc('m.end_date')
            ->orderByDesc('m.membership_id')
            ->first();
    }

    public function effectiveStatus(object $membership, Carbon $now): string
    {
        if (in_array($membership->status, self::INACTIVE_STATUSES, true)) {
            return $membership->status;
        }

        $today = $this->jakartaDate($now);

        if ($membership->end_date !== null && $membership->end_date < $today) {
            return 'expired';
        }

        if ($membership->type === 'single_visit' && (int) $membership->remaining_visits <= 0) {
            return 'expired';
        }

        return 'active';
    }

    public function jakartaDate(Carbon $now): string
    {
        return $now->copy()->setTimezone('Asia/Jakarta')->toDateString();
    }

    /**
     * @return array<int, object>
     */
    private function dateValid(int $userId, Carbon $now): array
    {
        $today = $this->jakartaDate($now);

        return $this->baseQuery($userId)
            ->where('m.start_date', '<=', $today)
            ->where(function (Builder $query) use ($today): void {
                $query->whereNull('m.end_date')->orWhere('m.end_date', '>=', $today);
            })
            ->orderByRaw("FIELD(m.type, 'single_visit', 'monthly_unlimited')")
            ->orderByRaw('m.end_date IS NULL')
            ->orderBy('m.end_date')
            ->orderBy('m.membership_id')
            ->get()
            ->all();
    }

    private function baseQuery(int $userId): Builder
    {
        return DB::table('memberships as m')
            ->join('packages as p', 'p.package_id', '=', 'm.package_id')
            ->where('m.user_id', $userId)
            ->select('m.*', 'p.name as package_name');
    }
}
