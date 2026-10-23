<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\ScanQrRequest;
use App\Models\User;
use App\Services\CheckInService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * FR-MEM-02..04: scan QR statis + geofence + guard sesi.
 */
final class AttendanceController extends Controller
{
    public function scan(ScanQrRequest $request, CheckInService $service): JsonResponse
    {
        $member = $request->user();

        if (! $member instanceof User) {
            throw ApiException::unauthenticated();
        }

        return ApiResponse::success(
            'Check-in berhasil. Silakan konfirmasi penggunaan loker ke kasir.',
            $service->scan($member, $request->validated()),
        );
    }
}
