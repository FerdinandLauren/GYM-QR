<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\ManualCheckInRequest;
use App\Models\User;
use App\Services\ManualCheckInService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * FR-CAS-05: check-in manual oleh kasir.
 */
final class ReceptionistManualCheckInController extends Controller
{
    public function store(ManualCheckInRequest $request, ManualCheckInService $service): JsonResponse
    {
        $staff = $request->user();

        if (! $staff instanceof User) {
            throw ApiException::unauthenticated();
        }

        return ApiResponse::success(
            'Check-in manual berhasil. Sesi kunjungan dibuat.',
            $service->checkIn($staff, $request->validated()),
        );
    }
}
