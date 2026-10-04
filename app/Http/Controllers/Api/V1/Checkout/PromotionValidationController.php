<?php

namespace App\Http\Controllers\Api\V1\Checkout;

use App\Http\Controllers\Controller;
use App\Services\CartService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Customer-side promotion check against the current cart.
 */
class PromotionValidationController extends Controller
{
    public function __construct(private readonly CartService $cartService) {}

    public function validate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'promotion_code' => ['required', 'string', 'max:50'],
        ]);

        $summary = $this->cartService->preview($request->user(), $validated['promotion_code']);

        return ApiResponse::success($summary, 'Promo valid');
    }
}
