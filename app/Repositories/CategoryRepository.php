<?php

namespace App\Repositories;

use App\Models\Category;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class CategoryRepository
{
    public function paginate(int $page, int $perPage, bool $onlyActive = true): LengthAwarePaginator
    {
        return Category::query()
            ->when($onlyActive, fn (Builder $q) => $q->where('is_active', true))
            ->withCount('products')
            ->orderBy('name')
            ->paginate($perPage, ['*'], 'page', $page);
    }

    public function findOrFail(int $id): Category
    {
        return Category::query()
            ->withCount('products')
            ->findOrFail($id);
    }

    public function slugExists(string $slug, ?int $ignoreId = null): bool
    {
        return Category::query()
            ->where('slug', $slug)
            ->when($ignoreId, fn (Builder $q) => $q->where('id', '!=', $ignoreId))
            ->exists();
    }

    public function productsCount(int $categoryId): int
    {
        return Category::query()->find($categoryId)?->products()->count() ?? 0;
    }
}
