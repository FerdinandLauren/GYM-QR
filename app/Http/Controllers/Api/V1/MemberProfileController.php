<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\MembershipResolver;
use App\Support\ApiResponse;
use App\Support\PhoneNumber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * FR-MEM-05/06: kartu digital, status paket, dan sesi aktif member.
 */
final class MemberProfileController extends Controller
{
    public function show(Request $request, MembershipResolver $resolver): JsonResponse
    {
        $member = $request->user();

        if (! $member instanceof User) {
            throw ApiException::unauthenticated();
        }

        $now = now();
        $membership = $resolver->forProfile($member->user_id, $now);
        $session = DB::table('attendance_logs as a')
            ->leftJoin('lockers as l', 'l.locker_id', '=', 'a.locker_id')
            ->where('a.user_id', $member->user_id)
            ->whereNull('a.checkout_time')
            ->orderByDesc('a.log_id')
            ->select('a.log_id', 'a.checkin_time', 'a.locker_state', 'l.locker_number')
            ->first();

        return ApiResponse::success('OK', [
            'user' => [
                'user_id' => $member->user_id,
                'full_name' => $member->full_name,
                'photo_url' => $member->photo_url,
                'phone_masked' => PhoneNumber::mask($member->phone_number),
            ],
            'membership' => $membership === null
                ? null
                : $this->membershipData($membership, $resolver, $now),
            'active_session' => $session === null ? null : [
                'log_id' => (int) $session->log_id,
                'checkin_time' => Carbon::parse((string) $session->checkin_time, 'UTC')->toIso8601ZuluString(),
                'locker_state' => (string) $session->locker_state,
                'locker_number' => $session->locker_number === null ? null : (string) $session->locker_number,
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function membershipData(object $membership, MembershipResolver $resolver, Carbon $now): array
    {
        $remainingDays = null;

        if ($membership->type === 'monthly_unlimited' && $membership->end_date !== null) {
            $remainingDays = max(0, (int) Carbon::parse($resolver->jakartaDate($now), 'Asia/Jakarta')
                ->diffInDays(Carbon::parse((string) $membership->end_date, 'Asia/Jakarta')));
        }

        return [
            'membership_id' => (int) $membership->membership_id,
            'type' => (string) $membership->type,
            'package_name' => (string) $membership->package_name,
            'effective_status' => $resolver->effectiveStatus($membership, $now),
            'remaining_days' => $remainingDays,
            'remaining_visits' => $membership->type === 'single_visit' ? (int) $membership->remaining_visits : null,
            'end_date' => $membership->end_date === null ? null : (string) $membership->end_date,
        ];
    }
}
