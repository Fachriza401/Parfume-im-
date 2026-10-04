<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Exceptions\BusinessRuleException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Order\UpdateOrderStatusRequest;
use App\Http\Requests\Order\UpdatePaymentRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\AuditLogService;
use App\Services\CheckoutService;
use App\Services\NotificationService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function __construct(
        private readonly CheckoutService $checkoutService,
        private readonly NotificationService $notificationService,
        private readonly AuditLogService $auditLog
    ) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['nullable', 'string', 'in:pending,processing,completed,cancelled'],
            'payment_status' => ['nullable', 'string', 'in:unpaid,paid,refunded'],
            'search' => ['nullable', 'string', 'max:100'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $query = Order::query()
            ->withCount('details')
            ->with([
                'user',
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

        if (! empty($validated['search'])) {
            $search = $validated['search'];
            $query->where(function ($q) use ($search) {
                $q->where('order_code', 'like', "%{$search}%")
                    ->orWhereHas('user', fn ($uq) => $uq->where('email', 'like', "%{$search}%"));
            });
        }

        if (! empty($validated['from'])) {
            $query->whereDate('created_at', '>=', $validated['from']);
        }

        if (! empty($validated['to'])) {
            $query->whereDate('created_at', '<=', $validated['to']);
        }

        $paginator = $query->paginate($validated['per_page'] ?? 15, ['*'], 'page', $validated['page'] ?? 1);

        return ApiResponse::paginated(
            $paginator,
            OrderResource::class,
            'Data berhasil diambil',
            array_filter($validated, fn ($value) => $value !== null)
        );
    }

    public function show(Order $order): JsonResponse
    {
        return ApiResponse::success(
            (new OrderResource($order->load(['user', 'details.product', 'payment', 'promotion'])))->resolve()
        );
    }

    public function updateStatus(UpdateOrderStatusRequest $request, Order $order): JsonResponse
    {
        $before = $order->status_order;
        $target = OrderStatus::from($request->validated('status_order'));

        $updated = $this->checkoutService->updateOrderStatus($order, $target);

        $this->auditLog->record(
            $request,
            action: 'order.status_update',
            module: 'order',
            description: "Status pesanan {$order->order_code} diubah dari {$before->value} menjadi {$target->value}",
            properties: [
                'order_id' => $order->id,
                'order_code' => $order->order_code,
                'from' => $before->value,
                'to' => $target->value,
            ]
        );

        $this->notificationService->notify(
            $order->user,
            'order_'.$target->value,
            $target->label(),
            "Pesanan {$order->order_code} kini berstatus {$target->label()}.",
            ['order_id' => $order->id, 'order_code' => $order->order_code]
        );

        return ApiResponse::success(
            (new OrderResource($updated->load(['user', 'details', 'payment'])))->resolve(),
            'Status pesanan berhasil diperbarui'
        );
    }

    public function updatePayment(UpdatePaymentRequest $request, Order $order): JsonResponse
    {
        $before = $order->status_payment;
        $beforeOrderStatus = $order->status_order;
        $target = PaymentStatus::from($request->validated('payment_status'));
        $payment = $order->payment;

        if (
            $target === PaymentStatus::PAID
            && in_array($payment?->payment_method, [PaymentMethod::BANK_TRANSFER, PaymentMethod::E_WALLET], true)
            && empty($payment->meta['receipt_path'])
        ) {
            throw BusinessRuleException::make(
                'Unggah bukti pembayaran sebelum memverifikasi pembayaran.',
                422,
                'PAYMENT_RECEIPT_REQUIRED'
            );
        }

        $updated = $this->checkoutService->updatePaymentStatus(
            $order,
            $target,
            $request->validated('transaction_id')
        );

        $this->auditLog->record(
            $request,
            action: 'order.payment_update',
            module: 'order',
            description: "Status pembayaran {$order->order_code} diubah dari {$before->value} menjadi {$target->value}",
            properties: [
                'order_id' => $order->id,
                'order_code' => $order->order_code,
                'from' => $before->value,
                'to' => $target->value,
            ]
        );

        $this->notificationService->notify(
            $order->user,
            'payment_'.$target->value,
            'Pembayaran '.$target->label(),
            "Status pembayaran pesanan {$order->order_code} kini {$target->label()}.",
            ['order_id' => $order->id, 'order_code' => $order->order_code]
        );

        if ($updated->status_order !== $beforeOrderStatus) {
            $this->auditLog->record(
                $request,
                action: 'order.status_update',
                module: 'order',
                description: "Status pesanan {$order->order_code} berubah otomatis dari {$beforeOrderStatus->value} menjadi {$updated->status_order->value} setelah pembayaran dikonfirmasi",
                properties: [
                    'order_id' => $order->id,
                    'order_code' => $order->order_code,
                    'from' => $beforeOrderStatus->value,
                    'to' => $updated->status_order->value,
                    'trigger' => 'payment_confirmation',
                ]
            );

            $this->notificationService->notify(
                $order->user,
                'order_'.$updated->status_order->value,
                $updated->status_order->label(),
                "Pembayaran terverifikasi. Pesanan {$order->order_code} kini berstatus {$updated->status_order->label()}.",
                ['order_id' => $order->id, 'order_code' => $order->order_code]
            );
        }

        return ApiResponse::success(
            (new OrderResource($updated->load(['user', 'details', 'payment'])))->resolve(),
            'Status pembayaran berhasil diperbarui'
        );
    }
}
