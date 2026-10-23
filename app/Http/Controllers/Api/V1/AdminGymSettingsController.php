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
 * FR-ADM-04: konfigurasi gym + regenerasi QR.
 */
final class AdminGymSettingsController extends Controller
{
    public function show(AdminService $service): JsonResponse
    {
        return ApiResponse::success('Pengaturan gym dimuat.', $service->gymSettings());
    }

    public function update(Request $request, AdminService $service): JsonResponse
    {
        $data = $request->validate([
            'gym_name' => ['sometimes', 'string', 'max:100'],
            'timezone' => ['sometimes', 'string', 'max:64'],
            'latitude' => ['sometimes', 'numeric', 'between:-90,90'],
            'longitude' => ['sometimes', 'numeric', 'between:-180,180'],
            'geofence_radius_m' => ['sometimes', 'integer', 'min:1', 'max:1000'],
            'geofence_accuracy_max_m' => ['sometimes', 'integer', 'min:1', 'max:1000'],
            'closing_time' => ['sometimes', 'date_format:H:i'],
        ]);

        return ApiResponse::success('Pengaturan gym diperbarui.', $service->updateGymSettings($this->admin(), $data));
    }

    public function regenerateQr(AdminService $service): JsonResponse
    {
        return ApiResponse::success(
            'QR baru dibuat. Cetak ulang QR fisik; payload lama tidak berlaku.',
            $service->regenerateQr($this->admin()),
        );
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
