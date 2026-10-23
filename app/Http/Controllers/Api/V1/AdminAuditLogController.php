<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\AdminService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * FR-ADM-06: penampil audit read-only.
 */
final class AdminAuditLogController extends Controller
{
    public function index(Request $request, AdminService $service): JsonResponse
    {
        $filters = $request->validate([
            'actor_user_id' => ['sometimes', 'integer', 'min:1'],
            'action' => ['sometimes', 'string', 'max:60'],
            'from' => ['sometimes', 'date_format:Y-m-d'],
            'to' => ['sometimes', 'date_format:Y-m-d'],
        ]);

        return ApiResponse::success('Audit log dimuat.', ['logs' => $service->auditLogs($filters)]);
    }
}
