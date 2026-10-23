<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\AdminService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * Daftar paket aktif untuk form penjualan kasir (FR-CAS-08).
 */
final class ReceptionistPackageController extends Controller
{
    public function index(AdminService $service): JsonResponse
    {
        $packages = array_values(array_filter(
            $service->packages(),
            fn (array $package): bool => $package['is_active'] === true,
        ));

        return ApiResponse::success('Daftar paket aktif dimuat.', ['packages' => $packages]);
    }
}
