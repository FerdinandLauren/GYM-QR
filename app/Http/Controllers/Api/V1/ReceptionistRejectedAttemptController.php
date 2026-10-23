<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * FR-CAS-11: scan ditolak hari ini agar tidak hilang saat kasir sibuk.
 */
final class ReceptionistRejectedAttemptController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date' => ['sometimes', 'date_format:Y-m-d'],
        ]);

        $date = $validated['date'] ?? Carbon::now('Asia/Jakarta')->toDateString();
        $start = Carbon::parse($date, 'Asia/Jakarta')->startOfDay()->utc();
        $end = Carbon::parse($date, 'Asia/Jakarta')->endOfDay()->utc();

        $rows = DB::table('audit_logs as a')
            ->leftJoin('users as u', 'u.user_id', '=', 'a.actor_user_id')
            ->where('a.action', 'attendance.scan_rejected')
            ->whereBetween('a.created_at', [$start, $end])
            ->orderByDesc('a.audit_id')
            ->get(['a.audit_id', 'a.actor_user_id', 'a.after_json', 'a.created_at', 'u.full_name']);

        $attempts = $rows->map(function (object $row): array {
            $after = $row->after_json === null ? [] : json_decode((string) $row->after_json, true);

            return [
                'audit_id' => (int) $row->audit_id,
                'user_id' => $row->actor_user_id === null ? null : (int) $row->actor_user_id,
                'full_name' => $row->full_name === null ? null : (string) $row->full_name,
                'reason_code' => (string) ($after['reason_code'] ?? 'UNKNOWN'),
                'occurred_at' => Carbon::parse((string) $row->created_at, 'UTC')->toIso8601ZuluString(),
            ];
        })->all();

        return ApiResponse::success('Daftar scan ditolak dimuat.', ['attempts' => $attempts]);
    }
}
