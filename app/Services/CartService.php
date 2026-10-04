<?php

namespace App\Services;

use App\Enums\DiscountType;
use App\Exceptions\BusinessRuleException;
use App\Exceptions\InsufficientStockException;
use App\Models\Cart;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\User;
use Illuminate\Support\Collection;

class CartService
{
    /**
     * @return Collection<int, Cart>
     */
    public function items(User $user): Collection
    {
        return Cart::query()
            ->where('user_id', $user->id)
            ->with(['product.category'])
            ->orderBy('id')
            ->get();
    }

    public function add(User $user, int $productId, int $quantity): Cart
    {
        $product = $this->findAvailableProduct($productId);

        $existing = Cart::query()
            ->where('user_id', $user->id)
            ->where('product_id', $product->id)
            ->first();

        $newQuantity = $quantity + ($existing?->quantity ?? 0);

        if (! $product->hasEnoughStock($newQuantity)) {
            throw new InsufficientStockException($product->name, (int) $product->stock, $newQuantity);
        }

        if ($existing) {
            $existing->update(['quantity' => $newQuantity]);

            return $existing->fresh();
        }

        return Cart::query()->create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'quantity' => $quantity,
        ]);
    }

    public function update(User $user, int $cartId, int $quantity): Cart
    {
        $cart = $this->findOwnedItem($user, $cartId);

        if ($quantity < 1) {
            throw BusinessRuleException::make('Jumlah minimal 1.', 422, 'INVALID_QUANTITY');
        }

        $product = $cart->product;

        if ($product === null || ! $product->is_active) {
            throw BusinessRuleException::make('Produk tidak tersedia.', 422, 'PRODUCT_UNAVAILABLE');
        }

        if (! $product->hasEnoughStock($quantity)) {
            throw new InsufficientStockException($product->name, (int) $product->stock, $quantity);
        }

        $cart->update(['quantity' => $quantity]);

        return $cart->fresh();
    }

    public function remove(User $user, int $cartId): void
    {
        $this->findOwnedItem($user, $cartId)->delete();
    }

    public function clear(User $user): void
    {
        Cart::query()->where('user_id', $user->id)->delete();
    }

    /**
     * Apply a promotion code to the cart preview (no persistence).
     *
     * @return array<string, mixed>
     */
    public function preview(User $user, ?string $code = null): array
    {
        $items = $this->items($user);
        $subtotal = (float) $items->sum(fn (Cart $item) => (float) $item->product->price * $item->quantity);

        $promotion = null;
        $discount = 0.0;

        if ($code !== null && trim($code) !== '') {
            $promotion = $this->resolvePromotion($code, $subtotal);
            $discount = $this->calculateDiscount($promotion, $subtotal);
        }

        return [
            'total_items' => (int) $items->sum('quantity'),
            'subtotal' => $subtotal,
            'discount_amount' => $discount,
            'final_amount' => max(0, $subtotal - $discount),
            'promotion' => $promotion?->only(['id', 'code', 'name', 'discount_type', 'discount_value']),
        ];
    }

    public function resolvePromotion(string $code, float $subtotal): Promotion
    {
        $promotion = Promotion::query()
            ->whereRaw('UPPER(code) = ?', [Promotion::normalizeCode($code)])
            ->first();

        if ($promotion === null) {
            throw BusinessRuleException::make(
                'Kode promo tidak ditemukan.',
                422,
                'INVALID_PROMOTION',
                ['promotion_code' => ['Kode promo tidak ditemukan.']]
            );
        }

        if (! $promotion->isUsableAt($subtotal)) {
            throw BusinessRuleException::make(
                'Kode promo tidak berlaku atau telah kedaluwarsa.',
                422,
                'INVALID_PROMOTION',
                ['promotion_code' => ['Kode promo tidak berlaku atau telah kedaluwarsa.']]
            );
        }

        return $promotion;
    }

    /**
     * Discount is never allowed to exceed the subtotal.
     */
    public function calculateDiscount(Promotion $promotion, float $subtotal): float
    {
        if ($subtotal <= 0) {
            return 0.0;
        }

        $discount = $promotion->discount_type === DiscountType::FIXED
            ? (float) $promotion->discount_value
            : $subtotal * ((float) $promotion->discount_value / 100);

        if ($promotion->max_discount_amount !== null) {
            $discount = min($discount, (float) $promotion->max_discount_amount);
        }

        return round(min($discount, $subtotal), 2);
    }

    public function findAvailableProduct(int $productId): Product
    {
        $product = Product::query()->available()->find($productId);

        if ($product === null) {
            throw BusinessRuleException::make(
                'Produk tidak ditemukan.',
                404,
                'PRODUCT_NOT_FOUND'
            );
        }

        return $product;
    }

    private function findOwnedItem(User $user, int $cartId): Cart
    {
        $cart = Cart::query()
            ->where('user_id', $user->id)
            ->with('product')
            ->find($cartId);

        if ($cart === null) {
            throw BusinessRuleException::make('Item keranjang tidak ditemukan.', 404, 'CART_ITEM_NOT_FOUND');
        }

        return $cart;
    }
}
