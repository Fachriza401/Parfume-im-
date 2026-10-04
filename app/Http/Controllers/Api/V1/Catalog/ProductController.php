<?php

namespace App\Http\Controllers\Api\V1\Catalog;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Services\CatalogService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function __construct(private readonly CatalogService $catalog) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:150'],
            'category' => ['nullable'],
            'min_price' => ['nullable', 'numeric', 'min:0'],
            'max_price' => ['nullable', 'numeric', 'min:0'],
            'in_stock' => ['nullable', 'in:true,false,1,0'],
            'sort' => ['nullable', 'string', 'in:latest,popular,price_asc,price_desc,rating,name'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $paginator = $this->catalog->listProducts($validated, $request);

        return ApiResponse::paginated(
            $paginator,
            ProductResource::class,
            'Data berhasil diambil',
            array_filter($validated, fn ($value) => $value !== null)
        );
    }

    public function show(Request $request, int $product): JsonResponse
    {
        $model = $this->catalog->showProduct($product, $request);

        return ApiResponse::success((new ProductResource($model))->resolve());
    }
}
