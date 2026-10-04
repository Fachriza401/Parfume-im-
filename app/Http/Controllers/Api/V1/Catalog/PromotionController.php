<?php

namespace App\Http\Controllers\Api\V1\Catalog;

use App\Http\Controllers\Controller;
use App\Http\Resources\PromotionResource;
use App\Models\Promotion;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Public list of promotions that customers can currently apply.
 *
 * Powers the scrolling promo ticker on the catalog home: only codes that are
 * active, inside their window and below the usage limit are exposed, so no
 * internal promotion data leaks to anonymous visitors.
 */
class PromotionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $limit = max(1, min($request->integer('limit', 8), 20));

        $promotions = Promotion::query()
            ->usable()
            ->orderByDesc('discount_value')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        return ApiResponse::success(PromotionResource::collection($promotions)->resolve());
    }
}
