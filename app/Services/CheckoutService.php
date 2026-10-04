<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Exceptions\BusinessRuleException;
use App\Exceptions\InsufficientStockException;
use App\Exceptions\InvalidStatusTransitionException;
use App\Models\Cart;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;

class CheckoutService
{
    public function __construct(
        private readonly CartService $cartService,
        private readonly NotificationService $notificationService,
        private readonly AuditLogService $auditLog
    ) {}

    /**
     * Creates an order in a single DB transaction. On any failure, everything rolls back.
     *
     * @param  array<string, mixed>  $data
     */
    public function checkout(User $user, array $data): Order
    {
        return DB::transaction(function () use ($user, $data): Order {
            $cartItems = $this->cartService->items($user);

            if ($cartItems->isEmpty()) {
                throw BusinessRuleException::make('Keranjang kosong.', 422, 'EMPTY_CART');
            }

            // Lock every product up front (in a stable id order to avoid deadlocks),
            // then validate against the locked rows so a concurrent checkout can
            // never drive stock negative (BR-03).
            $products = $this->lockProducts($cartItems);

            $this->validateStocks($cartItems, $products);

            $subtotal = (float) $cartItems->sum(function (Cart $item) use ($products): float {
                return (float) $products->get($item->product_id)->price * $item->quantity;
            });

            $promotion = null;
            $discount = 0.0;

            if (! empty($data['promotion_code'])) {
                $promotion = $this->cartService->resolvePromotion((string) $data['promotion_code'], $subtotal);
                $discount = $this->cartService->calculateDiscount($promotion, $subtotal);
            }

            $finalAmount = round(max(0, $subtotal - $discount), 2);

            $order = Order::query()->create([
                'order_code' => Order::generateCode(),
                'user_id' => $user->id,
                'promotion_id' => $promotion?->id,
                'status_order' => OrderStatus::PENDING,
                'status_payment' => PaymentStatus::UNPAID,
                'subtotal' => $subtotal,
                'discount_amount' => $discount,
                'final_amount' => $finalAmount,
                'shipping_name' => $data['shipping_name'],
                'shipping_phone' => $data['shipping_phone'],
                'shipping_address' => $data['shipping_address'],
                'shipping_city' => $data['shipping_city'],
                'shipping_postal_code' => $data['shipping_postal_code'],
                'note' => $data['note'] ?? null,
            ]);

            $this->createOrderDetails($order, $cartItems, $products);
            $this->decreaseStocks($cartItems, $products);
            $this->createPayment(
                $order,
                $data['payment_method'] ?? 'bank_transfer',
                $data['payment_destination_code'] ?? null
            );

            if ($promotion !== null) {
                $promotion->increment('used_count');
            }

            $this->cartService->clear($user);

            $this->notificationService->notify(
                $user,
                'order_created',
                'Pesanan berhasil dibuat',
                "Pesanan {$order->order_code} telah dibuat dan menunggu pembayaran.",
                ['order_id' => $order->id, 'order_code' => $order->order_code]
            );

            return $order->load(['details', 'payment']);
        });
    }

    public function updateOrderStatus(Order $order, OrderStatus $targetStatus): Order
    {
        return DB::transaction(function () use ($order, $targetStatus): Order {
            $order = Order::query()->lockForUpdate()->findOrFail($order->id);

            $current = $order->status_order;

            if ($current->isFinal() || ! $current->canTransitionTo($targetStatus)) {
                throw new InvalidStatusTransitionException($current->value, $targetStatus->value);
            }

            $updates = [
                'status_order' => $targetStatus,
            ];

            if ($targetStatus === OrderStatus::COMPLETED) {
                $updates['completed_at'] = now();
            }

            if ($targetStatus === OrderStatus::CANCELLED) {
                $updates['cancelled_at'] = now();
                $this->restoreStocks($order);
            }

            $order->update($updates);

            return $order->fresh();
        });
    }

    public function updatePaymentStatus(Order $order, PaymentStatus $targetStatus, ?string $transactionId = null): Order
    {
        return DB::transaction(function () use ($order, $targetStatus, $transactionId): Order {
            $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->id);
            $payment = Payment::query()->where('order_id', $lockedOrder->id)->lockForUpdate()->first();

            if ($payment === null) {
                throw BusinessRuleException::make(
                    'Data pembayaran pesanan tidak ditemukan.',
                    422,
                    'PAYMENT_NOT_FOUND'
                );
            }

            if (! $payment->payment_status->canTransitionTo($targetStatus)) {
                throw BusinessRuleException::make(
                    'Status pembayaran tidak dapat diubah.',
                    422,
                    'INVALID_PAYMENT_TRANSITION'
                );
            }

            $payment->update([
                'payment_status' => $targetStatus,
                'transaction_id' => $transactionId ?? $payment->transaction_id,
                'paid_at' => $targetStatus === PaymentStatus::PAID ? now() : $payment->paid_at,
                'refunded_at' => $targetStatus === PaymentStatus::REFUNDED ? now() : $payment->refunded_at,
            ]);

            $lockedOrder->update(['status_payment' => $targetStatus]);
            if (
                $targetStatus === PaymentStatus::PAID
                && $lockedOrder->status_order === OrderStatus::PENDING
            ) {
                $lockedOrder->update(['status_order' => OrderStatus::PROCESSING]);
            }

            return $lockedOrder->fresh(['payment']);
        });
    }

    /**
     * Locks every product referenced by the cart, keyed by product id.
     *
     * @param  EloquentCollection<int, Cart>  $cartItems
     * @return EloquentCollection<int, Product>
     */
    private function lockProducts(EloquentCollection $cartItems): EloquentCollection
    {
        $ids = $cartItems->pluck('product_id')
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();

        return Product::query()
            ->with('category')
            ->whereIn('id', $ids)
            ->lockForUpdate()
            ->get()
            ->keyBy('id');
    }

    /**
     * @param  EloquentCollection<int, Cart>  $cartItems
     * @param  EloquentCollection<int, Product>  $products
     */
    private function validateStocks(EloquentCollection $cartItems, EloquentCollection $products): void
    {
        foreach ($cartItems as $item) {
            $product = $products->get($item->product_id);

            if ($product === null || ! $product->is_active) {
                throw BusinessRuleException::make(
                    'Produk tidak tersedia.',
                    422,
                    'PRODUCT_UNAVAILABLE',
                    ['product_id' => [$item->product_id]]
                );
            }

            if (! $product->hasEnoughStock((int) $item->quantity)) {
                throw new InsufficientStockException($product->name, (int) $product->stock, (int) $item->quantity);
            }
        }
    }

    /**
     * @param  EloquentCollection<int, Cart>  $cartItems
     * @param  EloquentCollection<int, Product>  $products
     */
    private function createOrderDetails(Order $order, EloquentCollection $cartItems, EloquentCollection $products): void
    {
        foreach ($cartItems as $item) {
            $product = $products->get($item->product_id);
            $price = (float) $product->price;
            $quantity = (int) $item->quantity;

            OrderDetail::query()->create([
                'order_id' => $order->id,
                'product_id' => $product->id,
                'product_name_snapshot' => $product->name,
                'price_snapshot' => $price,
                'category_name_snapshot' => $product->category?->name,
                'quantity' => $quantity,
                'subtotal' => round($price * $quantity, 2),
            ]);
        }
    }

    /**
     * @param  EloquentCollection<int, Cart>  $cartItems
     * @param  EloquentCollection<int, Product>  $products
     */
    private function decreaseStocks(EloquentCollection $cartItems, EloquentCollection $products): void
    {
        foreach ($cartItems as $item) {
            $product = $products->get($item->product_id);
            $quantity = (int) $item->quantity;

            $product->decrement('stock', $quantity);
            $product->increment('sold_count', $quantity);
        }
    }

    private function restoreStocks(Order $order): void
    {
        $order->load(['details.product']);

        foreach ($order->details as $detail) {
            if ($detail->product_id === null || $detail->product === null) {
                continue;
            }

            $product = Product::query()->whereKey($detail->product_id)->lockForUpdate()->first();

            if ($product === null) {
                continue;
            }

            $product->increment('stock', (int) $detail->quantity);

            if ((int) $product->sold_count >= (int) $detail->quantity) {
                $product->decrement('sold_count', (int) $detail->quantity);
            } else {
                $product->update(['sold_count' => 0]);
            }
        }
    }

    private function createPayment(
        Order $order,
        string $paymentMethod,
        ?string $destinationCode
    ): void {
        $isBankTransfer = $paymentMethod === 'bank_transfer';
        $destinations = config($isBankTransfer ? 'payments.banks' : 'payments.e_wallets', []);
        $destination = collect($destinations)->firstWhere('code', $destinationCode);
        $reference = $paymentMethod === 'cod'
            ? null
            : 'TRX-'.now()->format('YmdHis').'-'.strtoupper(bin2hex(random_bytes(6)));

        Payment::query()->create([
            'order_id' => $order->id,
            'payment_method' => $paymentMethod,
            'payment_status' => PaymentStatus::UNPAID,
            'transaction_id' => $reference,
            'amount' => (float) $order->final_amount,
            'paid_at' => null,
            'refunded_at' => null,
            'meta' => $destination === null ? null : [
                'destination_code' => $destination['code'],
                'destination_name' => $destination['name'],
                'destination_account' => $destination['account_number'],
                'destination_account_name' => $destination['account_name'],
            ],
        ]);
    }
}
