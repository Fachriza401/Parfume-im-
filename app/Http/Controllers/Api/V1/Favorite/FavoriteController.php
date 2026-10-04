<?php

namespace App\Http\Controllers\Api\V1\Favorite;

use App\Exceptions\BusinessRuleException;
use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\Favorite;
use App\Models\Product;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FavoriteController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $favorites = Favorite::query()
            ->where('user_id', $request->user()->id)
            ->with('product.category')
            ->latest('id')
            ->get()
            ->pluck('product')
            ->filter()
            ->each(fn (Product $product) => $product->setAttribute('is_favorite', true));

        return ApiResponse::success(
            ProductResource::collection($favorites)->resolve()
        );
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
        ]);

        $productId = (int) $validated['product_id'];
        $product = Product::query()->find($productId);

        if ($product === null || ! $product->is_active) {
            throw BusinessRuleException::make(
                'Produk tidak tersedia.',
                422,
                'PRODUCT_UNAVAILABLE',
                ['product_id' => [$productId]]
            );
        }

        // Idempotent: favoriting twice must not raise a duplicate-key error.
        $created = Favorite::query()->firstOrCreate([
            'user_id' => $request->user()->id,
            'product_id' => $productId,
        ]);

        return ApiResponse::success(
            [],
            $created->wasRecentlyCreated ? 'Produk ditambahkan ke favorit' : 'Produk sudah ada di favorit',
            201
        );
    }

    public function destroy(Request $request, int $product): JsonResponse
    {
        $deleted = Favorite::query()
            ->where('user_id', $request->user()->id)
            ->where('product_id', $product)
            ->delete();

        if ($deleted === 0) {
            throw BusinessRuleException::make(
                'Produk tidak ada di daftar favorit.',
                404,
                'FAVORITE_NOT_FOUND'
            );
        }

        return ApiResponse::success([], 'Produk dihapus dari favorit');
    }
}
