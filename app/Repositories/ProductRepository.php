<?php

namespace App\Repositories;

use App\Models\Favorite;
use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class ProductRepository
{
    public const SORT_OPTIONS = [
        'latest' => 'created_at',
        'popular' => 'sold_count',
        'price_asc' => 'price',
        'price_desc' => 'price',
        'rating' => 'rating_avg',
        'name' => 'name',
    ];

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters, int $page, int $perPage): LengthAwarePaginator
    {
        $query = Product::query()->available()->with('category');

        $this->applySearch($query, $filters['search'] ?? null);

        if (! empty($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        if (! empty($filters['min_price'])) {
            $query->where('price', '>=', $filters['min_price']);
        }

        if (! empty($filters['max_price'])) {
            $query->where('price', '<=', $filters['max_price']);
        }

        if (! empty($filters['in_stock']) && filter_var($filters['in_stock'], FILTER_VALIDATE_BOOLEAN)) {
            $query->inStock();
        }

        $sort = $filters['sort'] ?? 'latest';
        $column = self::SORT_OPTIONS[$sort] ?? 'created_at';

        if ($sort === 'price_asc') {
            return $query
                ->orderBy($column)
                ->orderByDesc('id')
                ->paginate($perPage, ['*'], 'page', $page);
        }

        return $query
            ->orderByDesc($column)
            ->orderByDesc('id')
            ->paginate($perPage, ['*'], 'page', $page);
    }

    /**
     * Matches the term against the product name, its description and the
     * category name, so "cari aroma" also finds products filed under a scent
     * family instead of only matching the title.
     *
     * @param  Builder<Product>  $query
     */
    private function applySearch(Builder $query, mixed $search): void
    {
        if (empty($search) || ! is_string($search)) {
            return;
        }

        $term = trim($search);
        if ($term === '') {
            return;
        }

        $like = "%{$term}%";

        $query->where(function (Builder $q) use ($like): void {
            $q->where('products.name', 'like', $like)
                ->orWhere('products.description', 'like', $like)
                ->orWhereHas('category', fn (Builder $category) => $category->where('name', 'like', $like));
        });
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function findOrFail(int $id): Product
    {
        return Product::query()
            ->available()
            ->with('category')
            ->findOrFail($id);
    }

    /**
     * Ids of the given products the given user has favorited.
     *
     * @param  array<int, int>  $productIds
     * @return array<int, int>
     */
    public function favoriteIds(int $userId, array $productIds): array
    {
        if ($userId <= 0 || $productIds === []) {
            return [];
        }

        return Favorite::query()
            ->where('user_id', $userId)
            ->whereIn('product_id', $productIds)
            ->pluck('product_id')
            ->all();
    }
}
