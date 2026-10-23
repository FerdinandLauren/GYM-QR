<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\StaffLoginRequest;
use App\Models\User;
use App\Support\ApiResponse;
use App\Support\PhoneNumber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use PragmaRX\Google2FA\Google2FA;

/**
 * FR-CAS-12: password + TOTP wajib untuk admin, sesi 8 jam.
 */
final class StaffAuthController extends Controller
{
    public function login(StaffLoginRequest $request, Google2FA $google2fa): JsonResponse
    {
        $staff = User::query()
            ->where('phone_number', PhoneNumber::normalize((string) $request->validated('phone_number')))
            ->whereIn('role', ['receptionist', 'admin'])
            ->where('is_active', true)
            ->first();

        if ($staff === null || ! Hash::check((string) $request->validated('password'), (string) $staff->password_hash)) {
            throw ApiException::unauthenticated('Nomor atau password salah.');
        }

        if ($staff->role === 'admin') {
            $totpCode = (string) ($request->validated('totp_code') ?? '');

            if ($staff->totp_secret === null || $totpCode === '' || ! $google2fa->verifyKey((string) $staff->totp_secret, $totpCode, 2)) {
                throw ApiException::unauthenticated('Kode TOTP tidak valid.');
            }
        }

        Auth::guard('staff')->login($staff);
        $request->session()->regenerate();

        return ApiResponse::success('Login berhasil.', [
            'user' => [
                'user_id' => $staff->user_id,
                'full_name' => $staff->full_name,
                'role' => $staff->role,
            ],
            'expires_in' => (int) config('session.lifetime') * 60,
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::guard('staff')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return ApiResponse::success('Logout berhasil.');
    }
}
