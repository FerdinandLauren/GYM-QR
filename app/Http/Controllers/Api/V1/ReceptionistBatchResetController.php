<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\BatchResetService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * FR-CAS-09: reset loker massal saat jam tutup.
 */
final class ReceptionistBatchResetController extends Controller
{
    public function store(Request $request, BatchResetService $service): JsonResponse
    {
        $staff = $request->user();

        if (! $staff instanceof User) {
            throw ApiException::unauthenticated();
        }

        $data = $request->validate([
            'confirm_count' => ['required', 'integer', 'min:0'],
            'note' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);

        return ApiResponse::success(
            'Reset loker massal selesai.',
            $service->reset($staff, $data),
        );
    }
}
