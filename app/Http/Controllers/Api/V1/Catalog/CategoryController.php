<?php

namespace App\Http\Controllers\Api\V1\Catalog;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Repositories\CategoryRepository;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function __construct(private readonly CategoryRepository $categories) {}

    public function index(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->query('page', 1));
        $perPage = min(50, max(1, (int) $request->query('per_page', 50)));

        $paginator = $this->categories->paginate($page, $perPage);

        return ApiResponse::paginated(
            $paginator,
            CategoryResource::class,
            'Data berhasil diambil'
        );
    }

    public function show(int $category): JsonResponse
    {
        return ApiResponse::success(
            (new CategoryResource($this->categories->findOrFail($category)))->resolve()
        );
    }
}
