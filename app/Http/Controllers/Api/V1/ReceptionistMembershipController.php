<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\MembershipSaleRequest;
use App\Models\User;
use App\Services\MembershipService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * FR-CAS-08: daftar member baru, jual, dan perpanjang paket.
 */
final class ReceptionistMembershipController extends Controller
{
    public function store(MembershipSaleRequest $request, MembershipService $service): JsonResponse
    {
        $staff = $request->user();

        if (! $staff instanceof User) {
            throw ApiException::unauthenticated();
        }

        $data = $request->validated();

        return ApiResponse::success(
            'Paket berhasil dicatat.',
            $service->sell($staff, $data),
            $data['action'] === 'new' ? 201 : 200,
        );
    }
}
