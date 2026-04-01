<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;

class ApiResponse
{
    public static function success(
        string $message,
        mixed $data = null,
        int $status = 200,
        array $meta = [],
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
            'meta' => self::meta($meta),
        ], $status);
    }

    public static function created(string $message, mixed $data = null, array $meta = []): JsonResponse
    {
        return self::success($message, $data, 201, $meta);
    }

    public static function paginated(
        string $message,
        LengthAwarePaginator $paginator,
        array $data,
    ): JsonResponse {
        return self::success($message, $data, 200, [
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
        ]);
    }

    /**
     * Respuesta con cursor pagination (scroll infinito, alto desempeño).
     * No hace COUNT(*) — ideal para tablas grandes.
     */
    public static function cursor(
        string $message,
        CursorPaginator $paginator,
        array $data,
    ): JsonResponse {
        return self::success($message, $data, 200, [
            'pagination' => [
                'per_page'    => $paginator->perPage(),
                'next_cursor' => $paginator->nextCursor()?->encode(),
                'prev_cursor' => $paginator->previousCursor()?->encode(),
                'has_more'    => $paginator->hasMorePages(),
            ],
        ]);
    }

    public static function error(
        string $message,
        int $status,
        array $errors = [],
        array $meta = [],
    ): JsonResponse {
        $payload = [
            'success' => false,
            'message' => $message,
            'meta' => self::meta($meta),
        ];

        if ($errors !== []) {
            $payload['errors'] = $errors;
        }

        return response()->json($payload, $status);
    }

    /**
     * @param  array<string, mixed>  $errors
     */
    public static function validationError(array $errors, int $status = 422): JsonResponse
    {
        $message = Arr::first(Arr::flatten($errors)) ?? 'Validation failed.';

        return self::error((string) $message, $status, $errors);
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    private static function meta(array $meta = []): array
    {
        $requestId = request()?->attributes->get('request_id');

        return array_filter([
            'request_id' => $requestId,
            ...$meta,
        ], static fn (mixed $value): bool => $value !== null);
    }
}
