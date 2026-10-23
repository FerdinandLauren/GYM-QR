<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\LockerService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * FR-CAS-01/07: daftar sesi aktif hari ini + filter feed.
 */
final class ReceptionistSessionController extends Controller
{
    public function index(Request $request, LockerService $lockers): JsonResponse
    {
        $validated = $request->validate([
            'date' => ['sometimes', 'date_format:Y-m-d'],
            'filter' => ['sometimes', 'in:all,need_locker,assigned,no_locker,unverified'],
            'updated_since' => ['sometimes', 'date'],
        ]);

        $date = $validated['date'] ?? Carbon::now('Asia/Jakarta')->toDateString();

        return ApiResponse::success('Daftar sesi dimuat.', [
            'sessions' => $lockers->sessions([
                'date' => $date,
                'filter' => $validated['filter'] ?? 'all',
                'updated_since' => $validated['updated_since'] ?? null,
            ]),
        ]);
    }
}
