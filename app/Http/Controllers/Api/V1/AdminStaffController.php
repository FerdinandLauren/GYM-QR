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
 * FR-ADM-05: manajemen akun staf.
 */
final class AdminStaffController extends Controller
{
    public function index(AdminService $service): JsonResponse
    {
        return ApiResponse::success('Daftar staf dimuat.', ['staff' => $service->staff()]);
    }

    public function store(Request $request, AdminService $service): JsonResponse
    {
        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:100'],
            'phone_number' => ['required', 'string', 'max:20'],
            'role' => ['required', 'in:receptionist,admin'],
            'password' => ['required', 'string', 'min:8'],
            'totp_secret' => ['sometimes', 'nullable', 'string'],
        ]);

        return ApiResponse::success('Staf dibuat.', $service->createStaff($this->admin(), $data), 201);
    }

    public function update(Request $request, int $userId, AdminService $service): JsonResponse
    {
        $data = $request->validate([
            'full_name' => ['sometimes', 'string', 'max:100'],
            'role' => ['sometimes', 'in:receptionist,admin'],
            'is_active' => ['sometimes', 'boolean'],
            'password' => ['sometimes', 'string', 'min:8'],
        ]);

        return ApiResponse::success('Staf diperbarui.', $service->updateStaff($this->admin(), $userId, $data));
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
