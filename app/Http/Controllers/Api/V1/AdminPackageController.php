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
 * FR-ADM-01: CRUD paket (tanpa hapus permanen).
 */
final class AdminPackageController extends Controller
{
    public function index(AdminService $service): JsonResponse
    {
        return ApiResponse::success('Daftar paket dimuat.', ['packages' => $service->packages()]);
    }

    public function store(Request $request, AdminService $service): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:30'],
            'name' => ['required', 'string', 'max:100'],
            'type' => ['required', 'in:monthly_unlimited,single_visit'],
            'duration_days' => ['required', 'integer', 'min:1', 'max:3650'],
            'visit_quota' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'price' => ['required', 'numeric', 'min:0'],
        ]);

        return ApiResponse::success('Paket dibuat.', $service->createPackage($this->admin(), $data), 201);
    }

    public function update(Request $request, int $packageId, AdminService $service): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:100'],
            'duration_days' => ['sometimes', 'integer', 'min:1', 'max:3650'],
            'visit_quota' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'price' => ['sometimes', 'numeric', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        return ApiResponse::success('Paket diperbarui.', $service->updatePackage($this->admin(), $packageId, $data));
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
