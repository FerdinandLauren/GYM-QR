<?php

namespace App\Exceptions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * Mengubah semua error pada /api/* menjadi envelope PRD §7.2 dengan kode §7.3.
 */
final class ApiErrorRenderer
{
    public function render(Throwable $e, Request $request): ?JsonResponse
    {
        if (! $request->is('api/*')) {
            return null;
        }

        [$status, $code, $message, $details] = $this->describe($e, $request);

        $meta = ['request_id' => $request->header('X-Request-Id') ?: (string) Str::ulid()];

        if ($details !== null) {
            $meta['details'] = $details;
        }

        $response = response()->json([
            'status' => 'error',
            'code' => $code,
            'message' => $message,
            'data' => null,
            'meta' => $meta,
        ], $status);

        if ($e instanceof HttpExceptionInterface) {
            foreach ($e->getHeaders() as $name => $value) {
                $response->headers->set($name, $value);
            }
        }

        return $response;
    }

    /**
     * @return array{0: int, 1: string, 2: string, 3: array<string, mixed>|null}
     */
    private function describe(Throwable $e, Request $request): array
    {
        return match (true) {
            $e instanceof ValidationException => [
                400,
                'VALIDATION_ERROR',
                'Data yang dikirim tidak valid.',
                $e->errors(),
            ],
            $e instanceof AuthenticationException => [
                401,
                'UNAUTHENTICATED',
                'Sesi Anda berakhir. Silakan login kembali.',
                null,
            ],
            $e instanceof AuthorizationException => [
                403,
                'FORBIDDEN_ROLE',
                'Anda tidak berhak mengakses fitur ini.',
                null,
            ],
            $e instanceof ThrottleRequestsException => $this->throttle($e, $request),
            $e instanceof ModelNotFoundException, $e instanceof NotFoundHttpException => [
                404,
                'NOT_FOUND',
                'Data yang diminta tidak ditemukan.',
                null,
            ],
            $e instanceof HttpExceptionInterface => $this->http($e->getStatusCode()),
            default => [500, 'INTERNAL_ERROR', 'Terjadi kesalahan pada server. Coba lagi.', null],
        };
    }

    /**
     * @return array{0: int, 1: string, 2: string, 3: array<string, mixed>|null}
     */
    private function throttle(ThrottleRequestsException $e, Request $request): array
    {
        $code = $this->isOtpThrottle($request) ? 'OTP_RATE_LIMITED' : 'RATE_LIMITED';

        return [
            429,
            $code,
            'Terlalu banyak permintaan. Coba lagi nanti.',
            ['retry_after_seconds' => (int) ($e->getHeaders()['Retry-After'] ?? 0)],
        ];
    }

    /**
     * @return array{0: int, 1: string, 2: string, 3: array<string, mixed>|null}
     */
    private function http(int $status): array
    {
        return match ($status) {
            400, 422 => [400, 'VALIDATION_ERROR', 'Data yang dikirim tidak valid.', null],
            401 => [401, 'UNAUTHENTICATED', 'Sesi Anda berakhir. Silakan login kembali.', null],
            403 => [403, 'FORBIDDEN_ROLE', 'Anda tidak berhak mengakses fitur ini.', null],
            404 => [404, 'NOT_FOUND', 'Data yang diminta tidak ditemukan.', null],
            429 => [429, 'RATE_LIMITED', 'Terlalu banyak permintaan. Coba lagi nanti.', null],
            503 => [
                503,
                'SERVICE_UNAVAILABLE',
                'Layanan sedang tidak tersedia. Gunakan check-in manual di kasir.',
                null,
            ],
            default => [500, 'INTERNAL_ERROR', 'Terjadi kesalahan pada server. Coba lagi.', null],
        };
    }

    private function isOtpThrottle(Request $request): bool
    {
        foreach ($request->route()?->gatherMiddleware() ?? [] as $middleware) {
            if (is_string($middleware) && str_starts_with($middleware, 'throttle:otp')) {
                return true;
            }
        }

        return false;
    }
}
