<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\AdminMembershipRequest;
use App\Models\User;
use App\Services\MembershipService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * FR-ADM-07: suspend, aktifkan, batalkan, perpanjang membership.
 */
final class AdminMembershipController extends Controller
{
    public function update(AdminMembershipRequest $request, int $membershipId, MembershipService $service): JsonResponse
    {
        $admin = $request->user();

        if (! $admin instanceof User) {
            throw ApiException::unauthenticated();
        }

        return ApiResponse::success(
            'Membership diperbarui.',
            $service->adminAction($admin, $membershipId, $request->validated()),
        );
    }
}
