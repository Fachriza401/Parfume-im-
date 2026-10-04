<?php

namespace App\Http\Controllers\Api\V1\Checkout;

use App\Http\Controllers\Controller;
use App\Http\Requests\Checkout\CheckoutRequest;
use App\Http\Resources\OrderResource;
use App\Services\CheckoutService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class CheckoutController extends Controller
{
    public function __construct(private readonly CheckoutService $checkoutService) {}

    public function store(CheckoutRequest $request): JsonResponse
    {
        $order = $this->checkoutService->checkout($request->user(), $request->validated());

        return ApiResponse::success(
            (new OrderResource($order))->resolve(),
            'Pesanan berhasil dibuat',
            201
        );
    }
}
