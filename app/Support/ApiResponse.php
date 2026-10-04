<?php

namespace App\Support;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;

class ApiResponse
{
    /**
     * Standard success envelope.
     *
     * @param  array<string, mixed>|JsonResource|ResourceCollection  $data
     */
    public static function success(
        mixed $data = null,
        string $message = 'Data berhasil diambil',
        int $status = 200,
        array $extra = []
    ): JsonResponse {
        $payload = [
            'success' => true,
            'message' => $message,
            'data' => $data,
        ];

        if ($extra !== []) {
            $payload = array_merge($payload, $extra);
        }

        return response()->json($payload, $status);
    }

    /**
     * Standard error envelope.
     *
     * @param  array<string, mixed>  $errors
     */
    public static function error(
        string $message = 'Terjadi kesalahan',
        int $status = 400,
        array $errors = []
    ): JsonResponse {
        $payload = [
            'success' => false,
            'message' => $message,
        ];

        if ($errors !== []) {
            $payload['errors'] = $errors;
        }

        return response()->json($payload, $status);
    }

    /**
     * Paginated list envelope with a fixed shape for mobile clients.
     */
    public static function paginated(
        LengthAwarePaginator $paginator,
        string $resourceClass,
        string $message = 'Data berhasil diambil',
        array $query = []
    ): JsonResponse {
        return self::success(
            $resourceClass::collection($paginator->getCollection())->resolve(),
            $message,
            200,
            [
                'meta' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'from' => $paginator->firstItem(),
                    'to' => $paginator->lastItem(),
                ],
                'query' => $query,
            ]
        );
    }
}
