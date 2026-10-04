<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Exceptions\BusinessRuleException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Product\ProductRequest;
use App\Http\Requests\Product\StockAdjustmentRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Services\AuditLogService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    public function __construct(private readonly AuditLogService $auditLog) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:150'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'is_active' => ['nullable', 'in:true,false,1,0'],
            'trashed' => ['nullable', 'in:true,false,1,0'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $query = Product::query()->with('category')->withCount('orderDetails');

        if ($request->boolean('trashed')) {
            $query->onlyTrashed();
        } elseif (! $request->has('trashed')) {
            $query->withoutTrashed();
        }

        if (! empty($validated['search'])) {
            $term = trim($validated['search']);
            $like = "%{$term}%";

            $query->where(function ($q) use ($like) {
                $q->where('products.name', 'like', $like)
                    ->orWhere('products.description', 'like', $like)
                    ->orWhereHas('category', fn ($category) => $category->where('name', 'like', $like));
            });
        }

        if (! empty($validated['category_id'])) {
            $query->where('category_id', $validated['category_id']);
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $paginator = $query
            ->orderByDesc('id')
            ->paginate($validated['per_page'] ?? 15, ['*'], 'page', $validated['page'] ?? 1);

        return ApiResponse::paginated(
            $paginator,
            ProductResource::class,
            'Data berhasil diambil',
            array_filter($validated, fn ($value) => $value !== null)
        );
    }

    public function store(ProductRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['slug'] = $data['slug'] ?? Str::slug($data['name']);

        $product = Product::query()->create($data);

        $this->auditLog->record(
            $request,
            action: 'product.create',
            module: 'product',
            description: "Produk {$product->name} dibuat",
            properties: ['product_id' => $product->id, 'name' => $product->name]
        );

        return ApiResponse::success(
            (new ProductResource($product->load('category')))->resolve(),
            'Produk berhasil dibuat',
            201
        );
    }

    public function show(Product $product): JsonResponse
    {
        return ApiResponse::success(
            (new ProductResource($product->load('category')))->resolve()
        );
    }

    public function update(ProductRequest $request, Product $product): JsonResponse
    {
        $before = $product->only(['name', 'price', 'stock', 'is_active', 'category_id', 'image_url']);

        $data = $request->validated();
        $data['slug'] = $data['slug'] ?? $product->slug;

        $product->update($data);

        $this->auditLog->record(
            $request,
            action: 'product.update',
            module: 'product',
            description: "Produk {$product->name} diperbarui",
            properties: ['product_id' => $product->id, 'before' => $before, 'after' => $product->only(array_keys($before))]
        );

        return ApiResponse::success(
            (new ProductResource($product->fresh()->load('category')))->resolve(),
            'Produk berhasil diperbarui'
        );
    }

    /**
     * Soft delete, so historical orders keep working (BR-11).
     */
    public function destroy(Request $request, Product $product): JsonResponse
    {
        $name = $product->name;
        $product->delete();

        $this->auditLog->record(
            $request,
            action: 'product.delete',
            module: 'product',
            description: "Produk {$name} dihapus",
            properties: ['product_id' => $product->id, 'name' => $name]
        );

        return ApiResponse::success([], 'Produk berhasil dihapus');
    }

    public function adjustStock(StockAdjustmentRequest $request, Product $product): JsonResponse
    {
        $data = $request->validated();
        $before = (int) $product->stock;

        $newStock = match ($data['type']) {
            'in' => $before + (int) $data['quantity'],
            'out' => $before - (int) $data['quantity'],
            'set' => (int) $data['quantity'],
        };

        if ($newStock < 0) {
            throw BusinessRuleException::make(
                'Stok tidak boleh negatif.',
                422,
                'NEGATIVE_STOCK',
                ['stock' => ["Stok tersedia {$before}."]]
            );
        }

        $product->update(['stock' => $newStock]);

        $this->auditLog->record(
            $request,
            action: 'product.stock',
            module: 'product',
            description: "Stok {$product->name} diubah dari {$before} menjadi {$newStock}",
            properties: [
                'product_id' => $product->id,
                'before' => $before,
                'after' => $newStock,
                'type' => $data['type'],
                'reason' => $data['reason'] ?? null,
            ]
        );

        return ApiResponse::success(
            (new ProductResource($product->fresh()->load('category')))->resolve(),
            'Stok berhasil diperbarui'
        );
    }
}
