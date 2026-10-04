<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Exceptions\BusinessRuleException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Category\CategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use App\Services\AuditLogService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CategoryController extends Controller
{
    public function __construct(private readonly AuditLogService $auditLog) {}

    public function index(Request $request): JsonResponse
    {
        $categories = Category::query()
            ->withCount('products')
            ->when($request->filled('search'), fn ($q) => $q->where('name', 'like', '%'.$request->input('search').'%'))
            ->orderBy('name')
            ->get();

        return ApiResponse::success(CategoryResource::collection($categories)->resolve());
    }

    public function store(CategoryRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['slug'] = $data['slug'] ?? Str::slug($data['name']);

        $category = Category::query()->create($data);

        $this->auditLog->record(
            $request,
            action: 'category.create',
            module: 'category',
            description: "Kategori {$category->name} dibuat",
            properties: ['category_id' => $category->id, 'name' => $category->name]
        );

        return ApiResponse::success(
            (new CategoryResource($category))->resolve(),
            'Kategori berhasil dibuat',
            201
        );
    }

    public function show(Category $category): JsonResponse
    {
        return ApiResponse::success(
            (new CategoryResource($category->loadCount('products')))->resolve()
        );
    }

    public function update(CategoryRequest $request, Category $category): JsonResponse
    {
        $data = $request->validated();
        $data['slug'] = $data['slug'] ?? $category->slug;

        $category->update($data);

        $this->auditLog->record(
            $request,
            action: 'category.update',
            module: 'category',
            description: "Kategori {$category->name} diperbarui",
            properties: ['category_id' => $category->id, 'name' => $category->name]
        );

        return ApiResponse::success(
            (new CategoryResource($category->fresh()->loadCount('products')))->resolve(),
            'Kategori berhasil diperbarui'
        );
    }

    public function destroy(Request $request, Category $category): JsonResponse
    {
        // RESTRICT: a category with products must not be deleted.
        if ($category->products()->exists()) {
            throw BusinessRuleException::make(
                'Kategori masih memiliki produk. Pindahkan produk terlebih dahulu.',
                422,
                'CATEGORY_IN_USE'
            );
        }

        $name = $category->name;
        $category->delete();

        $this->auditLog->record(
            $request,
            action: 'category.delete',
            module: 'category',
            description: "Kategori {$name} dihapus",
            properties: ['name' => $name]
        );

        return ApiResponse::success([], 'Kategori berhasil dihapus');
    }
}
