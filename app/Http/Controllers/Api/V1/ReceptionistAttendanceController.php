<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\AssignLockerRequest;
use App\Models\User;
use App\Services\LockerService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * FR-CAS-02..06: aksi kasir pada sesi kunjungan.
 */
final class ReceptionistAttendanceController extends Controller
{
    public function __construct(private readonly LockerService $lockers) {}

    public function assignLocker(AssignLockerRequest $request, int $logId): JsonResponse
    {
        $staff = $request->user();

        if (! $staff instanceof User) {
            throw ApiException::unauthenticated();
        }

        $data = $this->lockers->assign($staff, $logId, (int) $request->validated('locker_id'));

        return ApiResponse::success(
            "Loker #{$data['locker_number']} berhasil di-assign ke member.",
            $data,
        );
    }

    public function releaseLocker(int $logId): JsonResponse
    {
        return ApiResponse::success(
            'Kunci loker dikembalikan. Sesi kunjungan ditutup.',
            $this->lockers->release($this->staff(), $logId),
        );
    }

    public function noLocker(int $logId): JsonResponse
    {
        return ApiResponse::success(
            'Sesi ditandai tanpa loker.',
            $this->lockers->markNoLocker($this->staff(), $logId),
        );
    }

    public function verifyIdentity(int $logId): JsonResponse
    {
        return ApiResponse::success(
            'Identitas member dikonfirmasi.',
            $this->lockers->verifyIdentity($this->staff(), $logId),
        );
    }

    private function staff(): User
    {
        $staff = request()->user();

        if (! $staff instanceof User) {
            throw ApiException::unauthenticated();
        }

        return $staff;
    }
}
