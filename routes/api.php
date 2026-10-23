<?php

use App\Http\Controllers\Api\V1\AdminAuditLogController;
use App\Http\Controllers\Api\V1\AdminGymSettingsController;
use App\Http\Controllers\Api\V1\AdminLockerController;
use App\Http\Controllers\Api\V1\AdminMembershipController;
use App\Http\Controllers\Api\V1\AdminPackageController;
use App\Http\Controllers\Api\V1\AdminStaffController;
use App\Http\Controllers\Api\V1\AttendanceController;
use App\Http\Controllers\Api\V1\MemberAttendanceHistoryController;
use App\Http\Controllers\Api\V1\MemberAuthController;
use App\Http\Controllers\Api\V1\MemberProfileController;
use App\Http\Controllers\Api\V1\ReceptionistAttendanceController;
use App\Http\Controllers\Api\V1\ReceptionistBatchResetController;
use App\Http\Controllers\Api\V1\ReceptionistLockerController;
use App\Http\Controllers\Api\V1\ReceptionistManualCheckInController;
use App\Http\Controllers\Api\V1\ReceptionistMemberController;
use App\Http\Controllers\Api\V1\ReceptionistMembershipController;
use App\Http\Controllers\Api\V1\ReceptionistRejectedAttemptController;
use App\Http\Controllers\Api\V1\ReceptionistSessionController;
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

    Route::middleware(['auth:staff', 'role:receptionist,admin'])->prefix('receptionist')->group(function (): void {
        Route::get('lockers', [ReceptionistLockerController::class, 'index']);
        Route::get('sessions', [ReceptionistSessionController::class, 'index']);
        Route::get('members', [ReceptionistMemberController::class, 'index']);
        Route::get('rejected-attempts', [ReceptionistRejectedAttemptController::class, 'index']);
        Route::post('manual-checkin', [ReceptionistManualCheckInController::class, 'store'])
            ->middleware('throttle:manual-checkin');
        Route::post('memberships', [ReceptionistMembershipController::class, 'store']);
        Route::post('lockers/batch-reset', [ReceptionistBatchResetController::class, 'store']);
        Route::patch('attendance/{logId}/assign-locker', [ReceptionistAttendanceController::class, 'assignLocker'])
            ->whereNumber('logId')
            ->middleware('throttle:assign-locker');
        Route::patch('attendance/{logId}/release-locker', [ReceptionistAttendanceController::class, 'releaseLocker'])
            ->whereNumber('logId');
        Route::patch('attendance/{logId}/no-locker', [ReceptionistAttendanceController::class, 'noLocker'])
            ->whereNumber('logId');
        Route::patch('attendance/{logId}/verify-identity', [ReceptionistAttendanceController::class, 'verifyIdentity'])
            ->whereNumber('logId');
    });

    Route::middleware(['auth:staff', 'role:admin'])->prefix('admin')->group(function (): void {
        Route::patch('memberships/{membershipId}', [AdminMembershipController::class, 'update'])
            ->whereNumber('membershipId');
        Route::get('packages', [AdminPackageController::class, 'index']);
        Route::post('packages', [AdminPackageController::class, 'store']);
        Route::patch('packages/{packageId}', [AdminPackageController::class, 'update'])->whereNumber('packageId');
        Route::get('lockers', [AdminLockerController::class, 'index']);
        Route::post('lockers', [AdminLockerController::class, 'store']);
        Route::patch('lockers/{lockerId}', [AdminLockerController::class, 'update'])->whereNumber('lockerId');
        Route::get('gym-settings', [AdminGymSettingsController::class, 'show']);
        Route::patch('gym-settings', [AdminGymSettingsController::class, 'update']);
        Route::post('gym-settings/regenerate-qr', [AdminGymSettingsController::class, 'regenerateQr']);
        Route::get('staff', [AdminStaffController::class, 'index']);
        Route::post('staff', [AdminStaffController::class, 'store']);
        Route::patch('staff/{userId}', [AdminStaffController::class, 'update'])->whereNumber('userId');
        Route::get('audit-logs', [AdminAuditLogController::class, 'index']);
    });
});
