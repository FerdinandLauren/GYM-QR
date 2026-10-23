<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\RefreshTokenRequest;
use App\Http\Requests\RequestOtpRequest;
use App\Http\Requests\VerifyOtpRequest;
use App\Models\User;
use App\Services\MemberAuthService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * FR-MEM-01 dan FR-MEM-10. Base URL PRD §7.1.
 */
final class MemberAuthController extends Controller
{
    public function requestOtp(RequestOtpRequest $request, MemberAuthService $service): JsonResponse
    {
        $service->requestOtp(
            (string) $request->validated('email'),
            $request->ip(),
        );

        return ApiResponse::success('Kode OTP telah dikirim ke email Anda.', ['expires_in' => 300]);
    }

    public function verifyOtp(VerifyOtpRequest $request, MemberAuthService $service): JsonResponse
    {
        $tokens = $service->verifyOtp(
            (string) $request->validated('email'),
            (string) $request->validated('code'),
        );

        return ApiResponse::success('Login berhasil.', $tokens);
    }

    public function refresh(RefreshTokenRequest $request, MemberAuthService $service): JsonResponse
    {
        return ApiResponse::success('Token diperbarui.', $service->refresh((string) $request->validated('refresh_token')));
    }

    public function logout(Request $request, MemberAuthService $service): JsonResponse
    {
        $member = $request->user();

        if (! $member instanceof User) {
            throw ApiException::unauthenticated();
        }

        $service->logout($member);

        return ApiResponse::success('Logout berhasil.', ['refresh_token_revoked' => true]);
    }
}
