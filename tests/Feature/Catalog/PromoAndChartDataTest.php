<?php

namespace Tests\Feature\Catalog;

use App\Enums\DiscountType;
use App\Enums\OrderStatus;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Covers the data feeding the promo ticker and the three admin charts.
 */
class PromoAndChartDataTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    // ------------------------------------------------------------------ Ticker

    public function test_promo_ticker_endpoint_is_public(): void
    {
        Promotion::factory()->create([
            'code' => 'HEMAT10',
            'is_active' => true,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
        ]);

        $this->getJson('/api/v1/promotions')
            ->assertOk()
            ->assertJsonPath('data.0.code', 'HEMAT10');
    }

    public function test_promo_ticker_endpoint_hides_unusable_promotions(): void
    {
        Promotion::factory()->inactive()->create(['code' => 'MATI']);
        Promotion::factory()->expired()->create(['code' => 'LEWAT']);
        Promotion::factory()->create([
            'code' => 'KEPUNG',
            'usage_limit' => 5,
            'used_count' => 5,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
        ]);

        $this->getJson('/api/v1/promotions')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_promo_ticker_endpoint_returns_discount_fields(): void
    {
        Promotion::factory()->fixed(25000)->create([
            'code' => 'FIXED',
            'min_order_amount' => 100000,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
        ]);

        $this->getJson('/api/v1/promotions')
            ->assertOk()
            ->assertJsonPath('data.0.discount_type', DiscountType::FIXED->value)
            ->assertJsonPath('data.0.discount_value', 25000)
            ->assertJsonPath('data.0.min_order_amount', 100000);
    }

    public function test_promo_ticker_limit_is_capped(): void
    {
        Promotion::factory()->count(25)->create([
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
        ]);

        $this->getJson('/api/v1/promotions?limit=5000')
            ->assertOk()
            ->assertJsonCount(20, 'data');
    }

    // ------------------------------------------------------------------ Charts

    public function test_sales_report_returns_chart_payloads(): void
    {
        $admin = $this->admin();
        $category = Category::factory()->create(['name' => 'Floral']);
        $product = Product::factory()->create(['category_id' => $category->id]);

        $order = Order::factory()->paid()->create([
            'subtotal' => 200000,
            'final_amount' => 200000,
        ]);
        OrderDetail::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'category_name_snapshot' => 'Floral',
            'quantity' => 2,
            'price_snapshot' => 100000,
            'subtotal' => 200000,
        ]);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/admin/reports/sales?preset=month')
            ->assertOk()
            ->assertJsonPath('data.total_sales', 200000)
            ->assertJsonPath('data.top_categories.0.name', 'Floral')
            ->assertJsonPath('data.top_categories.0.revenue', 200000)
            ->assertJsonPath('data.top_categories.0.sold', 2)
            ->assertJsonPath('data.best_selling.0.sold', 2)
            ->assertJsonStructure([
                'data' => [
                    'period',
                    'sales_by_date',
                    'orders_by_status',
                ],
            ]);
    }

    public function test_sales_report_orders_by_status_covers_every_status(): void
    {
        Order::factory()->create(['status_order' => OrderStatus::PENDING]);
        Order::factory()->status(OrderStatus::PROCESSING)->create();

        $response = $this->actingAs($this->admin(), 'sanctum')
            ->getJson('/api/v1/admin/reports/sales?preset=month')
            ->assertOk();

        $byStatus = $response->json('data.orders_by_status');

        $this->assertSame(1, $byStatus[OrderStatus::PENDING->value]);
        $this->assertSame(1, $byStatus[OrderStatus::PROCESSING->value]);
        $this->assertSame(0, $byStatus[OrderStatus::COMPLETED->value]);
        $this->assertSame(0, $byStatus[OrderStatus::CANCELLED->value]);
    }

    public function test_sales_report_respects_period_filter(): void
    {
        $admin = $this->admin();

        Order::factory()->paid()->create([
            'created_at' => now()->subDays(40),
            'final_amount' => 50000,
        ]);
        Order::factory()->paid()->create(['final_amount' => 75000]);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/admin/reports/sales?preset=month')
            ->assertOk()
            ->assertJsonPath('data.total_sales', 75000)
            ->assertJsonPath('data.total_orders', 1);
    }

    public function test_sales_report_rejects_unknown_preset(): void
    {
        $this->actingAs($this->admin(), 'sanctum')
            ->getJson('/api/v1/admin/reports/sales?preset=decade')
            ->assertStatus(422)
            ->assertJsonValidationErrors('preset');
    }

    public function test_sales_report_requires_admin(): void
    {
        $this->actingAs(User::factory()->create(), 'sanctum')
            ->getJson('/api/v1/admin/reports/sales')
            ->assertForbidden();
    }

    public function test_dashboard_stats_expose_chart_series(): void
    {
        $this->actingAs($this->admin(), 'sanctum')
            ->getJson('/api/v1/admin/dashboard-stats')
            ->assertOk()
            ->assertJsonStructure([
                'data' => ['sales_by_date', 'top_categories', 'best_selling'],
            ]);
    }

    // ------------------------------------------------------------------ Search

    public function test_admin_product_search_matches_name_description_and_category(): void
    {
        $admin = $this->admin();
        $category = Category::factory()->create(['name' => 'Woody']);

        Product::factory()->create([
            'name' => 'Aroma Malam',
            'description' => 'wangi kayu Docs',
        ]);
        Product::factory()->create([
            'name' => 'Produk Lain',
            'category_id' => $category->id,
            'description' => null,
        ]);
        Product::factory()->create(['name' => 'Lain', 'description' => 'tidak cocok']);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/admin/products?search=Woody')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        // The description branch resolves the same single row.
        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/admin/products?search=wangi kayu')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_admin_product_search_keeps_category_filter_and_active_filter(): void
    {
        $admin = $this->admin();
        $floral = Category::factory()->create(['name' => 'Floral']);
        $woody = Category::factory()->create(['name' => 'Woody']);

        Product::factory()->create(['name' => 'Match Floral', 'category_id' => $floral->id]);
        Product::factory()->create(['name' => 'Match Woody', 'category_id' => $woody->id]);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/admin/products?search=Match&category_id='.$floral->id)
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_catalog_search_matches_category_name(): void
    {
        $category = Category::factory()->create(['name' => 'Floral']);

        Product::factory()->create(['name' => 'A', 'category_id' => $category->id]);
        Product::factory()->create(['name' => 'B']);

        $this->getJson('/api/v1/products?search=Floral')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    /**
     * The catalog screen sends these keys; the admin product list is always
     * newest-first and takes no `sort` parameter at all.
     *
     * @return array<int, array{0: string}>
     */
    public static function supportedSortKeys(): array
    {
        return [['latest'], ['popular'], ['price_asc'], ['price_desc'], ['rating'], ['name']];
    }

    #[DataProvider('supportedSortKeys')]
    public function test_catalog_accepts_supported_sort_keys(string $sort): void
    {
        $this->getJson('/api/v1/products?sort='.$sort)->assertOk();
    }

    /**
     * `name_asc` was what the old Flutter dropdown sent; the endpoint rejects it
     * with a 422, which is why the client must use the keys above.
     */
    public function test_catalog_rejects_unsupported_sort_key(): void
    {
        $this->getJson('/api/v1/products?sort=name_asc')
            ->assertStatus(422)
            ->assertJsonValidationErrors('sort');
    }
}
