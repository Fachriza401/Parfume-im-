<?php

namespace App\Http\Controllers\Api\V1\Cart;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cart\StoreCartRequest;
use App\Http\Requests\Cart\UpdateCartRequest;
use App\Http\Resources\CartResource;
use App\Models\User;
use App\Services\CartService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function __construct(private readonly CartService $cartService) {}

    public function index(Request $request): JsonResponse
    {
        return ApiResponse::success($this->cartPayload($request->user()));
    }

    public function store(StoreCartRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $item = $this->cartService->add(
            $request->user(),
            (int) $validated['product_id'],
            (int) $validated['quantity']
        );

        $user = $request->user();

        return ApiResponse::success([
            'item' => (new CartResource($item->load('product.category')))->resolve(),
            ...$this->cartPayload($user),
        ], 'Produk ditambahkan ke keranjang', 201);
    }

    public function update(UpdateCartRequest $request, int $cart): JsonResponse
    {
        $item = $this->cartService->update(
            $request->user(),
            $cart,
            (int) $request->validated('quantity')
        );

        $user = $request->user();

        return ApiResponse::success([
            'item' => (new CartResource($item->load('product.category')))->resolve(),
            ...$this->cartPayload($user),
        ], 'Keranjang diperbarui');
    }

    public function destroy(Request $request, int $cart): JsonResponse
    {
        $this->cartService->remove($request->user(), $cart);

        return ApiResponse::success($this->cartPayload($request->user()), 'Produk dihapus dari keranjang');
    }

    public function applyPromotion(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'promotion_code' => ['required', 'string', 'max:50'],
        ]);

        return ApiResponse::success(
            $this->cartPayload($request->user(), $validated['promotion_code']),
            'Promo berhasil diterapkan'
        );
    }

    /** Keep every cart response in the same shape for client-side synchronization. */
    private function cartPayload(User $user, ?string $promotionCode = null): array
    {
        $summary = $this->cartService->preview($user, $promotionCode);

        return [
            'items' => CartResource::collection($this->cartService->items($user))->resolve(),
            'summary' => $summary,
            'promotion' => $summary['promotion'],
        ];
    }
}
