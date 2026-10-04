<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\Category;
use App\Models\OrderDetail;
use App\Models\Product;
use App\Models\Rating;
use App\Repositories\ProductRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

class CatalogService
{
    public function __construct(private readonly ProductRepository $products) {}

    /**
     * @param  array<string, mixed>  $query
     */
    public function listProducts(array $query, ?Request $request = null): LengthAwarePaginator
    {
        $page = max(1, (int) ($query['page'] ?? 1));
        $perPage = min(50, max(1, (int) ($query['per_page'] ?? 15)));

        $filters = [
            'search' => $query['search'] ?? null,
            'category_id' => $this->resolveCategoryId($query),
            'min_price' => $query['min_price'] ?? null,
            'max_price' => $query['max_price'] ?? null,
            'in_stock' => $query['in_stock'] ?? null,
            'sort' => $query['sort'] ?? 'latest',
        ];

        $paginator = $this->products->paginate($filters, $page, $perPage);

        $this->attachFavoriteFlags($paginator, $request);

        return $paginator;
    }

    public function showProduct(int $id, ?Request $request = null): Product
    {
        $product = $this->products->findOrFail($id);

        $userId = $request?->user()?->id;

        if ($userId !== null) {
            $product->is_favorite = in_array(
                $product->id,
                $this->products->favoriteIds($userId, [$product->id]),
                true
            );
            $product->can_rate = $this->canRate($userId, $product->id);
        }

        return $product;
    }

    /**
     * A product may be rated once it belongs to a completed order and has no
     * rating from that customer yet (BR-08).
     */
    private function canRate(int $userId, int $productId): bool
    {
        $alreadyRated = Rating::query()
            ->where('user_id', $userId)
            ->where('product_id', $productId)
            ->exists();

        if ($alreadyRated) {
            return false;
        }

        return OrderDetail::query()
            ->where('product_id', $productId)
            ->whereHas('order', fn ($query) => $query
                ->where('user_id', $userId)
                ->where('status_order', OrderStatus::COMPLETED->value))
            ->exists();
    }

    private function resolveCategoryId(array $query): ?int
    {
        $category = $query['category'] ?? null;

        if ($category === null || $category === '') {
            return null;
        }

        if (is_numeric($category)) {
            return (int) $category;
        }

        return Category::query()->where('slug', $category)->value('id');
    }

    /**
     * Flag which products the current user has favorited, in one query.
     */
    private function attachFavoriteFlags(LengthAwarePaginator $paginator, ?Request $request): void
    {
        if ($request === null || ($userId = $request->user()?->id) === null) {
            return;
        }

        $ids = $paginator->getCollection()->pluck('id')->all();
        $favoriteIds = $this->products->favoriteIds($userId, $ids);

        $paginator->getCollection()->each(function (Product $product) use ($favoriteIds) {
            $product->is_favorite = in_array($product->id, $favoriteIds, true);
        });
    }
}
