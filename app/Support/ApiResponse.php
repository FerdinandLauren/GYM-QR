<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use stdClass;

/**
 * Envelope sukses PRD §7.2.
 */
final class ApiResponse
{
    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $meta
     */
    public static function success(string $message, array $data = [], int $status = 200, array $meta = []): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'message' => $message,
            'data' => $data === [] ? new stdClass : $data,
            'meta' => ['request_id' => self::requestId()] + $meta,
        ], $status);
    }

    private static function requestId(): string
    {
        $header = request()->header('X-Request-Id');

        return is_string($header) && $header !== '' ? $header : (string) Str::ulid();
    }
}
