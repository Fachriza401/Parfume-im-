<?php

namespace App\Http\Controllers\Api\V1\Order;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\CheckoutService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function __construct(private readonly CheckoutService $checkoutService) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['nullable', 'string', 'in:pending,processing,completed,cancelled'],
            'payment_status' => ['nullable', 'string', 'in:unpaid,paid,refunded'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $user = $request->user();

        $query = Order::query()
            ->where('user_id', $user->id)
            ->withCount('details')
            ->with([
                'details' => fn ($details) => $details
                    ->select(['id', 'order_id', 'product_id', 'product_name_snapshot', 'category_name_snapshot', 'price_snapshot', 'quantity', 'subtotal'])
                    ->limit(3),
                'payment',
            ])
            ->latest('id');

        if (! empty($validated['status'])) {
            $query->where('status_order', $validated['status']);
        }

        if (! empty($validated['payment_status'])) {
            $query->where('status_payment', $validated['payment_status']);
        }

        $paginator = $query->paginate(
            perPage: $validated['per_page'] ?? 15,
            page: $validated['page'] ?? 1
        );

        return ApiResponse::paginated(
            $paginator,
            OrderResource::class,
            'Data berhasil diambil',
            array_filter($validated, fn ($value) => $value !== null)
        );
    }

    public function show(Request $request, int $order): JsonResponse
    {
        $model = Order::query()
            ->where('user_id', $request->user()->id)
            ->with(['details.product', 'payment', 'promotion'])
            ->findOrFail($order);

        return ApiResponse::success((new OrderResource($model))->resolve());
    }

    public function cancel(Request $request, int $order): JsonResponse
    {
        $model = Order::query()
            ->where('user_id', $request->user()->id)
            ->lockForUpdate()
            ->findOrFail($order);

        $updated = $this->checkoutService->updateOrderStatus(
            $model,
            OrderStatus::CANCELLED
        );

        return ApiResponse::success((new OrderResource($updated))->resolve());
    }
}
