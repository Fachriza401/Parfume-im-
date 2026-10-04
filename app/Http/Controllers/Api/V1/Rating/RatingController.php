<?php

namespace App\Http\Controllers\Api\V1\Rating;

use App\Enums\OrderStatus;
use App\Exceptions\BusinessRuleException;
use App\Http\Controllers\Controller;
use App\Http\Resources\RatingResource;
use App\Models\OrderDetail;
use App\Models\Product;
use App\Models\Rating;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RatingController extends Controller
{
    public function index(Request $request, int $product): JsonResponse
    {
        $perPage = min(50, max(1, (int) $request->integer('per_page', 15)));
        $page = max(1, (int) $request->integer('page', 1));

        $paginator = Rating::query()
            ->where('product_id', $product)
            ->with('user')
            ->latest('id')
            ->paginate($perPage, ['*'], 'page', $page);

        return ApiResponse::paginated(
            $paginator,
            RatingResource::class,
            'Data berhasil diambil',
            ['product_id' => $product]
        );
    }

    public function store(Request $request, int $product): JsonResponse
    {
        $validated = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'review' => ['nullable', 'string', 'max:1000'],
        ]);

        $user = $request->user();

        // A rating requires a completed order, so only delivered products count.
        $orderDetail = OrderDetail::query()
            ->where('product_id', $product)
            ->whereHas('order', function ($query) use ($user) {
                $query->where('user_id', $user->id)
                    ->where('status_order', OrderStatus::COMPLETED->value);
            })
            ->latest('id')
            ->first();

        if ($orderDetail === null) {
            throw BusinessRuleException::make(
                'Anda hanya bisa memberikan rating untuk produk dari pesanan yang sudah selesai.',
                422,
                'PURCHASE_REQUIRED'
            );
        }

        // Upsert: re-submitting replaces the previous review instead of dead-ending.
        $rating = Rating::query()->updateOrCreate(
            [
                'user_id' => $user->id,
                'product_id' => $product,
            ],
            [
                'order_id' => $orderDetail->order_id,
                'rating' => (int) $validated['rating'],
                'review' => $validated['review'] ?? null,
            ]
        );

        $this->updateProductRating($product);

        return ApiResponse::success(
            (new RatingResource($rating->load('user')))->resolve(),
            $rating->wasRecentlyCreated ? 'Rating berhasil dikirim' : 'Rating berhasil diperbarui',
            201
        );
    }

    private function updateProductRating(int $productId): void
    {
        $stats = Rating::query()
            ->where('product_id', $productId)
            ->selectRaw('COUNT(*) as count, AVG(rating) as avg')
            ->first();

        if ($stats === null) {
            return;
        }

        Product::query()->whereKey($productId)->update([
            'rating_count' => (int) $stats->count,
            'rating_avg' => round((float) $stats->avg, 2),
        ]);
    }
}
