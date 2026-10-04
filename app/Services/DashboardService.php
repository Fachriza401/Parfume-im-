<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    public function stats(?string $from = null, ?string $to = null): array
    {
        $toDate = $to ? now()->parse($to)->endOfDay() : now();
        $fromDate = $from ? now()->parse($from)->startOfDay() : $toDate->copy()->subDays(29)->startOfDay();

        $paidOrders = fn () => Order::query()
            ->where('status_payment', PaymentStatus::PAID->value)
            ->whereBetween('created_at', [$fromDate, $toDate]);

        $revenueTotal = (float) $paidOrders()->sum('final_amount');
        $totalOrders = Order::query()->whereBetween('created_at', [$fromDate, $toDate])->count();

        return [
            'period' => [
                'from' => $fromDate->toDateString(),
                'to' => $toDate->toDateString(),
            ],
            'revenue' => [
                'total' => $revenueTotal,
                'today' => (float) Order::query()
                    ->where('status_payment', PaymentStatus::PAID->value)
                    ->whereDate('created_at', today())
                    ->sum('final_amount'),
                'this_month' => (float) Order::query()
                    ->where('status_payment', PaymentStatus::PAID->value)
                    ->whereBetween('created_at', [now()->startOfMonth(), now()])
                    ->sum('final_amount'),
            ],
            'orders' => [
                'total' => (int) Order::query()->count(),
                'pending' => (int) Order::query()->where('status_order', OrderStatus::PENDING->value)->count(),
                'processing' => (int) Order::query()->where('status_order', OrderStatus::PROCESSING->value)->count(),
                'completed' => (int) Order::query()->where('status_order', OrderStatus::COMPLETED->value)->count(),
                'cancelled' => (int) Order::query()->where('status_order', OrderStatus::CANCELLED->value)->count(),
            ],
            'payments' => [
                'unpaid' => (int) Order::query()->where('status_payment', PaymentStatus::UNPAID->value)->count(),
                'paid' => (int) Order::query()->where('status_payment', PaymentStatus::PAID->value)->count(),
                'refunded' => (int) Order::query()->where('status_payment', PaymentStatus::REFUNDED->value)->count(),
            ],
            'products' => [
                'total' => (int) Product::query()->count(),
                'active' => (int) Product::query()->available()->count(),
                'out_of_stock' => (int) Product::query()->where('stock', 0)->count(),
            ],
            'customers' => [
                'total' => (int) User::query()->customers()->count(),
                'new_this_month' => (int) User::query()
                    ->customers()
                    ->whereBetween('created_at', [now()->startOfMonth(), now()])
                    ->count(),
            ],
            'average_order_value' => $totalOrders > 0 ? round($revenueTotal / max(1, $this->paidCount($fromDate, $toDate)), 2) : 0,
            'best_selling' => $this->bestSelling($fromDate, $toDate),
            'top_categories' => $this->topCategories($fromDate, $toDate),
            'sales_by_date' => $this->salesByDate($fromDate, $toDate),
            'recent_orders' => $this->recentOrders(),
        ];
    }

    private function paidCount($from, $to): int
    {
        return Order::query()
            ->where('status_payment', PaymentStatus::PAID->value)
            ->whereBetween('created_at', [$from, $to])
            ->count();
    }

    private function bestSelling($from, $to): array
    {
        return DB::table('order_details')
            ->join('orders', 'orders.id', '=', 'order_details.order_id')
            ->where('orders.status_payment', PaymentStatus::PAID->value)
            ->whereBetween('orders.created_at', [$from, $to])
            ->groupBy('order_details.product_id')
            ->orderByDesc(DB::raw('SUM(order_details.quantity)'))
            ->limit(5)
            ->get([
                'order_details.product_id',
                DB::raw('MAX(order_details.product_name_snapshot) as name'),
                DB::raw('SUM(order_details.quantity) as sold'),
                DB::raw('SUM(order_details.subtotal) as revenue'),
            ])
            ->map(fn ($row) => [
                'product_id' => (int) $row->product_id,
                'name' => $row->name,
                'sold' => (int) $row->sold,
                'revenue' => (float) $row->revenue,
            ])
            ->all();
    }

    private function topCategories($from, $to): array
    {
        return DB::table('order_details')
            ->join('orders', 'orders.id', '=', 'order_details.order_id')
            ->leftJoin('products', 'products.id', '=', 'order_details.product_id')
            ->leftJoin('categories', 'categories.id', '=', 'products.category_id')
            ->where('orders.status_payment', PaymentStatus::PAID->value)
            ->whereBetween('orders.created_at', [$from, $to])
            ->groupBy('categories.id')
            ->orderByDesc(DB::raw('SUM(order_details.quantity)'))
            ->limit(5)
            ->get([
                'categories.id as category_id',
                DB::raw('MAX(order_details.category_name_snapshot) as name'),
                DB::raw('SUM(order_details.quantity) as sold'),
                DB::raw('SUM(order_details.subtotal) as revenue'),
            ])
            ->map(fn ($row) => [
                'category_id' => $row->category_id === null ? null : (int) $row->category_id,
                'name' => $row->name,
                'sold' => (int) $row->sold,
                'revenue' => (float) $row->revenue,
            ])
            ->all();
    }

    private function salesByDate($from, $to): array
    {
        return DB::table('orders')
            ->select([
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(*) as orders'),
            ])
            // DB::raw() ignores bindings, so the parameterised column must use selectRaw().
            ->selectRaw('SUM(CASE WHEN status_payment = ? THEN final_amount ELSE 0 END) as revenue', [PaymentStatus::PAID->value])
            ->whereBetween('created_at', [$from, $to])
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(fn ($row) => [
                'date' => $row->date,
                'orders' => (int) $row->orders,
                'revenue' => (float) $row->revenue,
            ])
            ->all();
    }

    private function recentOrders(): array
    {
        return Order::query()
            ->with('user')
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn (Order $order) => [
                'id' => $order->id,
                'order_code' => $order->order_code,
                'customer' => $order->user?->email,
                'final_amount' => (float) $order->final_amount,
                'status_order' => $order->status_order->value,
                'status_payment' => $order->status_payment->value,
                'created_at' => optional($order->created_at)->toISOString(),
            ])
            ->all();
    }
}
