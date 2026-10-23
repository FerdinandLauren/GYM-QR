<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AdminService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * FR-ADM-02: tambah/ubah status loker.
 */
final class AdminLockerController extends Controller
{
    public function index(AdminService $service): JsonResponse
    {
        return ApiResponse::success('Daftar loker dimuat.', ['lockers' => $service->lockers()]);
    }

    public function store(Request $request, AdminService $service): JsonResponse
    {
        $data = $request->validate([
            'locker_number' => ['required', 'string', 'max:10'],
        ]);

        return ApiResponse::success('Loker dibuat.', $service->createLocker($this->admin(), $data), 201);
    }

    public function update(Request $request, int $lockerId, AdminService $service): JsonResponse
    {
        $data = $request->validate([
            'locker_number' => ['sometimes', 'string', 'max:10'],
            'status' => ['sometimes', 'in:available,out_of_service'],
            'reason' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);

        return ApiResponse::success('Loker diperbarui.', $service->updateLocker($this->admin(), $lockerId, $data));
    }

    private function admin(): User
    {
        $admin = request()->user();

        if (! $admin instanceof User) {
            throw ApiException::unauthenticated();
        }

        return $admin;
    }
}
