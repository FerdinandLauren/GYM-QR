<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\LockerService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * FR-CAS-07: status seluruh loker untuk dinding loker.
 */
final class ReceptionistLockerController extends Controller
{
    public function index(LockerService $lockers): JsonResponse
    {
        return ApiResponse::success('Status loker dimuat.', $lockers->lockers());
    }
}
