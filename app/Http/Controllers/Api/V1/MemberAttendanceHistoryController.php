<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * FR-MEM-09: 30 kunjungan terakhir milik member yang login.
 */
final class MemberAttendanceHistoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $member = $request->user();

        if (! $member instanceof User) {
            throw ApiException::unauthenticated();
        }

        $rows = DB::table('attendance_logs as a')
            ->leftJoin('lockers as l', 'l.locker_id', '=', 'a.locker_id')
            ->where('a.user_id', $member->user_id)
            ->orderByDesc('a.checkin_time')
            ->orderByDesc('a.log_id')
            ->limit(30)
            ->select('a.log_id', 'a.checkin_time', 'a.checkout_time', 'l.locker_number')
            ->get();

        $data = [];

        foreach ($rows as $row) {
            $data[] = [
                'log_id' => (int) $row->log_id,
                'checkin_time' => Carbon::parse((string) $row->checkin_time, 'UTC')->toIso8601ZuluString(),
                'checkout_time' => $row->checkout_time === null
                    ? null
                    : Carbon::parse((string) $row->checkout_time, 'UTC')->toIso8601ZuluString(),
                'locker_number' => $row->locker_number === null ? null : (string) $row->locker_number,
            ];
        }

        return ApiResponse::success('OK', $data);
    }
}
