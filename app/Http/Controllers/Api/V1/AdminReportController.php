<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\ReportService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * FR-ADM-03: laporan kunjungan, peak hours, ekspor CSV.
 */
final class AdminReportController extends Controller
{
    public function attendance(Request $request, ReportService $service): JsonResponse|Response
    {
        $data = $request->validate([
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
            'group' => ['sometimes', 'in:day,week,month'],
            'format' => ['sometimes', 'in:json,csv'],
        ]);

        $rows = $service->attendance($data['from'], $data['to'], $data['group'] ?? 'day');

        if (($data['format'] ?? 'json') === 'csv') {
            return response($service->csv($rows), 200, [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="attendance.csv"',
            ]);
        }

        return ApiResponse::success('Laporan kunjungan dimuat.', ['rows' => $rows]);
    }

    public function peakHours(Request $request, ReportService $service): JsonResponse
    {
        $data = $request->validate([
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);

        return ApiResponse::success('Peak hours dimuat.', [
            'hours' => $service->peakHours($data['from'], $data['to']),
        ]);
    }
}
