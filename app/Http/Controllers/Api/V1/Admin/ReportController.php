<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function sales(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'preset' => ['nullable', 'string', 'in:today,week,month,custom'],
        ]);

        [$from, $to] = $this->resolveRange($validated);

        $paidOrders = fn () => Order::query()
            ->where('status_payment', PaymentStatus::PAID->value)
            ->whereBetween('created_at', [$from, $to]);

        $totalOrders = (int) Order::query()->whereBetween('created_at', [$from, $to])->count();
        $totalSales = (float) $paidOrders()->sum('final_amount');

        return ApiResponse::success([
            'period' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'total_sales' => $totalSales,
            'total_paid_orders' => (int) $paidOrders()->count(),
            'total_orders' => $totalOrders,
            'average_order_value' => $totalOrders > 0 ? round($totalSales / $totalOrders, 2) : 0,
            'sales_by_date' => $this->salesByDate($from, $to),
            'best_selling' => $this->bestSelling($from, $to),
            'top_categories' => $this->topCategories($from, $to),
            'orders_by_status' => $this->ordersByStatus($from, $to),
        ]);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array{0: Carbon, 1: Carbon}
     */
    private function resolveRange(array $validated): array
    {
        $preset = $validated['preset'] ?? null;

        return match ($preset) {
            'today' => [today()->startOfDay(), today()->endOfDay()],
            'week' => [now()->startOfWeek(), now()->endOfDay()],
            'month' => [now()->startOfMonth(), now()->endOfDay()],
            default => [
                isset($validated['from']) ? now()->parse($validated['from'])->startOfDay() : now()->subDays(29)->startOfDay(),
                isset($validated['to']) ? now()->parse($validated['to'])->endOfDay() : now()->endOfDay(),
            ],
        };
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

    private function bestSelling($from, $to): array
    {
        return DB::table('order_details')
            ->join('orders', 'orders.id', '=', 'order_details.order_id')
            ->where('orders.status_payment', PaymentStatus::PAID->value)
            ->whereBetween('orders.created_at', [$from, $to])
            ->groupBy('order_details.product_id')
            ->orderByDesc(DB::raw('SUM(order_details.quantity)'))
            ->limit(10)
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

    /**
     * Revenue share per category, used by the pie chart on the reports screen.
     *
     * @return array<int, array<string, mixed>>
     */
    private function topCategories($from, $to): array
    {
        return DB::table('order_details')
            ->join('orders', 'orders.id', '=', 'order_details.order_id')
            ->leftJoin('products', 'products.id', '=', 'order_details.product_id')
            ->leftJoin('categories', 'categories.id', '=', 'products.category_id')
            ->where('orders.status_payment', PaymentStatus::PAID->value)
            ->whereBetween('orders.created_at', [$from, $to])
            ->groupBy('categories.id')
            ->orderByDesc(DB::raw('SUM(order_details.subtotal)'))
            ->limit(6)
            ->get([
                'categories.id as category_id',
                DB::raw('MAX(order_details.category_name_snapshot) as name'),
                DB::raw('SUM(order_details.quantity) as sold'),
                DB::raw('SUM(order_details.subtotal) as revenue'),
            ])
            ->map(fn ($row) => [
                'category_id' => $row->category_id === null ? null : (int) $row->category_id,
                'name' => $row->name ?? 'Tanpa kategori',
                'sold' => (int) $row->sold,
                'revenue' => (float) $row->revenue,
            ])
            ->all();
    }

    /**
     * Order counts grouped by workflow status, used by the donut chart.
     *
     * @return array<string, int>
     */
    private function ordersByStatus($from, $to): array
    {
        $rows = Order::query()
            ->select('status_order', DB::raw('COUNT(*) as total'))
            ->whereBetween('created_at', [$from, $to])
            ->groupBy('status_order')
            ->pluck('total', 'status_order');

        $result = [];
        foreach (OrderStatus::cases() as $status) {
            $result[$status->value] = (int) ($rows[$status->value] ?? 0);
        }

        return $result;
    }
}
