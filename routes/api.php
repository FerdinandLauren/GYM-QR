<?php

use App\Http\Controllers\Api\V1\AttendanceController;
use App\Http\Controllers\Api\V1\MemberAttendanceHistoryController;
use App\Http\Controllers\Api\V1\MemberAuthController;
use App\Http\Controllers\Api\V1\MemberProfileController;
use App\Http\Controllers\Api\V1\StaffAuthController;
use Illuminate\Support\Facades\Route;

// Base URL PRD §7.1: /api/v1.
Route::prefix('v1')->group(function (): void {
    Route::post('auth/request-otp', [MemberAuthController::class, 'requestOtp'])->middleware('throttle:otp-request');
    Route::post('auth/verify-otp', [MemberAuthController::class, 'verifyOtp'])->middleware('throttle:otp-verify');
    Route::post('auth/refresh', [MemberAuthController::class, 'refresh'])->middleware('throttle:otp-verify');
    Route::post('auth/logout', [MemberAuthController::class, 'logout'])->middleware('auth:member');

    Route::middleware('auth:member')->group(function (): void {
        Route::get('member/profile', [MemberProfileController::class, 'show']);
        Route::get('member/attendance/history', [MemberAttendanceHistoryController::class, 'index']);
        Route::post('attendance/scan-qr', [AttendanceController::class, 'scan'])->middleware('throttle:scan-qr');
    });

    Route::post('receptionist/auth/login', [StaffAuthController::class, 'login'])->middleware('throttle:staff-login');
    Route::post('receptionist/auth/logout', [StaffAuthController::class, 'logout'])
        ->middleware(['auth:staff', 'role:receptionist,admin']);
});
