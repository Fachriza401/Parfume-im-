<?php

namespace Tests\Feature\Admin;

use App\Enums\DiscountType;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Cart;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminCrudTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    public function test_admin_can_create_category(): void
    {
        $this->actingAs($this->admin(), 'sanctum')
            ->postJson('/api/v1/admin/categories', ['name' => 'Floral', 'description' => 'Wangi bunga'])
            ->assertStatus(201)
            ->assertJsonPath('data.name', 'Floral')
            ->assertJsonPath('data.slug', 'floral');

        $this->assertDatabaseHas('categories', ['name' => 'Floral']);
    }

    public function test_category_name_must_be_unique(): void
    {
        Category::factory()->create(['name' => 'Floral']);

        $this->actingAs($this->admin(), 'sanctum')
            ->postJson('/api/v1/admin/categories', ['name' => 'Floral'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('name');
    }

    public function test_admin_can_update_category(): void
    {
        $category = Category::factory()->create(['name' => 'Floral']);

        $this->actingAs($this->admin(), 'sanctum')
            ->putJson('/api/v1/admin/categories/'.$category->id, ['name' => 'Floral Baru'])
            ->assertStatus(200)
            ->assertJsonPath('data.name', 'Floral Baru');

        $this->assertDatabaseHas('categories', ['id' => $category->id, 'name' => 'Floral Baru']);
    }

    public function test_admin_can_delete_unused_category(): void
    {
        $category = Category::factory()->create();

        $this->actingAs($this->admin(), 'sanctum')
            ->deleteJson('/api/v1/admin/categories/'.$category->id)
            ->assertStatus(200);

        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
    }

    public function test_category_with_products_cannot_be_deleted(): void
    {
        $category = Category::factory()->create();
        Product::factory()->create(['category_id' => $category->id]);

        $this->actingAs($this->admin(), 'sanctum')
            ->deleteJson('/api/v1/admin/categories/'.$category->id)
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_admin_can_create_product(): void
    {
        $category = Category::factory()->create();

        $this->actingAs($this->admin(), 'sanctum')
            ->postJson('/api/v1/admin/products', [
                'name' => 'Oud Noir',
                'category_id' => $category->id,
                'price' => 450000,
                'stock' => 10,
                'description' => 'Eau de parfum oriental',
                'image_url' => '/images/uploads/oud.jpg',
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.name', 'Oud Noir')
            ->assertJsonPath('data.price', 450000)
            ->assertJsonPath('data.image_url', '/images/uploads/oud.jpg');

        $this->assertDatabaseHas('products', ['name' => 'Oud Noir', 'stock' => 10]);
    }

    public function test_admin_can_change_product_category_and_replace_its_image(): void
    {
        $oldCategory = Category::factory()->create();
        $newCategory = Category::factory()->create();
        $product = Product::factory()->create([
            'category_id' => $oldCategory->id,
            'image_url' => '/images/uploads/old.jpg',
        ]);

        $this->actingAs($this->admin(), 'sanctum')
            ->putJson('/api/v1/admin/products/'.$product->id, [
                'name' => $product->name,
                'category_id' => $newCategory->id,
                'price' => $product->price,
                'stock' => $product->stock,
                'image_url' => '/images/uploads/new.jpg',
                'is_active' => true,
            ])
            ->assertOk()
            ->assertJsonPath('data.category.id', $newCategory->id)
            ->assertJsonPath('data.image_url', '/images/uploads/new.jpg');

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'category_id' => $newCategory->id,
            'image_url' => '/images/uploads/new.jpg',
        ]);
    }

    public function test_product_price_cannot_be_negative(): void
    {
        $category = Category::factory()->create();

        $this->actingAs($this->admin(), 'sanctum')
            ->postJson('/api/v1/admin/products', [
                'name' => 'Buruk',
                'category_id' => $category->id,
                'price' => -1,
                'stock' => 1,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('price');
    }

    public function test_product_stock_cannot_be_negative(): void
    {
        $category = Category::factory()->create();

        $this->actingAs($this->admin(), 'sanctum')
            ->postJson('/api/v1/admin/products', [
                'name' => 'Buruk',
                'category_id' => $category->id,
                'price' => 1000,
                'stock' => -5,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('stock');
    }

    public function test_product_requires_existing_category(): void
    {
        $this->actingAs($this->admin(), 'sanctum')
            ->postJson('/api/v1/admin/products', [
                'name' => 'Tanpa Kategori',
                'category_id' => 9999,
                'price' => 1000,
                'stock' => 1,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('category_id');
    }

    public function test_admin_can_soft_delete_product(): void
    {
        $product = Product::factory()->create();

        $this->actingAs($this->admin(), 'sanctum')
            ->deleteJson('/api/v1/admin/products/'.$product->id)
            ->assertStatus(200);

        $this->assertSoftDeleted('products', ['id' => $product->id]);
    }

    public function test_trashed_products_are_listed_for_admin(): void
    {
        $product = Product::factory()->create();
        $product->delete();

        $this->actingAs($this->admin(), 'sanctum')
            ->getJson('/api/v1/admin/products?trashed=true')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_admin_can_adjust_stock_in(): void
    {
        $product = Product::factory()->create(['stock' => 5]);

        $this->actingAs($this->admin(), 'sanctum')
            ->postJson('/api/v1/admin/products/'.$product->id.'/stock', [
                'type' => 'in',
                'quantity' => 10,
                'reason' => 'Restock',
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.stock', 15);

        $this->assertSame(15, (int) $product->fresh()->stock);
    }

    public function test_admin_cannot_adjust_stock_below_zero(): void
    {
        $product = Product::factory()->create(['stock' => 5]);

        $this->actingAs($this->admin(), 'sanctum')
            ->postJson('/api/v1/admin/products/'.$product->id.'/stock', [
                'type' => 'out',
                'quantity' => 10,
            ])
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->assertSame(5, (int) $product->fresh()->stock);
    }

    public function test_stock_can_be_set_exactly(): void
    {
        $product = Product::factory()->create(['stock' => 5]);

        $this->actingAs($this->admin(), 'sanctum')
            ->postJson('/api/v1/admin/products/'.$product->id.'/stock', [
                'type' => 'set',
                'quantity' => 42,
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.stock', 42);
    }

    public function test_stock_adjustment_requires_valid_type(): void
    {
        $product = Product::factory()->create();

        $this->actingAs($this->admin(), 'sanctum')
            ->postJson('/api/v1/admin/products/'.$product->id.'/stock', [
                'type' => 'kurang',
                'quantity' => 1,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('type');
    }

    public function test_admin_can_create_promotion(): void
    {
        $this->actingAs($this->admin(), 'sanctum')
            ->postJson('/api/v1/admin/promotions', [
                'code' => 'HEMAT10',
                'name' => 'Diskon 10 Persen',
                'discount_type' => DiscountType::PERCENTAGE->value,
                'discount_value' => 10,
                'image_url' => '/images/uploads/promo.jpg',
                'starts_at' => now()->toDateTimeString(),
                'ends_at' => now()->addWeek()->toDateTimeString(),
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.code', 'HEMAT10')
            ->assertJsonPath('data.image_url', '/images/uploads/promo.jpg');

        $this->assertDatabaseHas('promotions', [
            'code' => 'HEMAT10',
            'image_url' => '/images/uploads/promo.jpg',
        ]);
    }

    public function test_new_active_promotion_notifies_customers_but_not_admins(): void
    {
        $admin = $this->admin();
        $customer = User::factory()->create();

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/admin/promotions', [
                'code' => 'BARU10',
                'name' => 'Diskon Baru',
                'discount_type' => DiscountType::PERCENTAGE->value,
                'discount_value' => 10,
                'starts_at' => now()->toDateTimeString(),
                'ends_at' => now()->addWeek()->toDateTimeString(),
                'is_active' => true,
            ])
            ->assertStatus(201);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $customer->id,
            'type' => 'promotion',
            'title' => 'Promo baru: Diskon Baru',
        ]);
        $this->assertDatabaseMissing('notifications', [
            'user_id' => $admin->id,
            'type' => 'promotion',
        ]);
    }

    public function test_promotion_code_must_be_unique(): void
    {
        Promotion::create([
            'code' => 'DUP',
            'name' => 'Duplikat',
            'discount_type' => DiscountType::FIXED,
            'discount_value' => 1000,
        ]);

        $this->actingAs($this->admin(), 'sanctum')
            ->postJson('/api/v1/admin/promotions', [
                'code' => 'DUP',
                'name' => 'Duplikat Lagi',
                'discount_type' => DiscountType::FIXED,
                'discount_value' => 1000,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('code');
    }

    public function test_percentage_promotion_value_cannot_exceed_hundred(): void
    {
        $this->actingAs($this->admin(), 'sanctum')
            ->postJson('/api/v1/admin/promotions', [
                'code' => 'GILA',
                'name' => 'Kelewat',
                'discount_type' => DiscountType::PERCENTAGE->value,
                'discount_value' => 150,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('discount_value');
    }

    public function test_admin_can_delete_promotion(): void
    {
        $promotion = Promotion::create([
            'code' => 'HAPUS',
            'name' => 'Hapus',
            'discount_type' => DiscountType::FIXED,
            'discount_value' => 1000,
        ]);

        $this->actingAs($this->admin(), 'sanctum')
            ->deleteJson('/api/v1/admin/promotions/'.$promotion->id)
            ->assertStatus(200);

        $this->assertDatabaseMissing('promotions', ['id' => $promotion->id]);
    }

    public function test_promotion_validation_endpoint_reports_valid_code(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['price' => 100000, 'stock' => 5]);

        Promotion::create([
            'code' => 'GOOD',
            'name' => 'Baik',
            'discount_type' => DiscountType::PERCENTAGE,
            'discount_value' => 10,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
            'is_active' => true,
        ]);

        Cart::create(['user_id' => $user->id, 'product_id' => $product->id, 'quantity' => 1]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/promotions/validate', ['promotion_code' => 'GOOD'])
            ->assertStatus(200)
            ->assertJsonPath('data.subtotal', 100000)
            ->assertJsonPath('data.discount_amount', 10000)
            ->assertJsonPath('data.final_amount', 90000)
            ->assertJsonPath('data.promotion.code', 'GOOD');
    }

    public function test_promotion_validation_endpoint_rejects_invalid_code(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['price' => 100000, 'stock' => 5]);

        Cart::create(['user_id' => $user->id, 'product_id' => $product->id, 'quantity' => 1]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/promotions/validate', ['promotion_code' => 'NOPE'])
            ->assertStatus(422)
            ->assertJsonPath('errors.code', 'INVALID_PROMOTION');
    }

    public function test_promotion_validation_requires_code(): void
    {
        $this->actingAs(User::factory()->create(), 'sanctum')
            ->postJson('/api/v1/promotions/validate', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('promotion_code');
    }

    public function test_customer_cannot_create_product(): void
    {
        $category = Category::factory()->create();

        $this->actingAs(User::factory()->create(), 'sanctum')
            ->postJson('/api/v1/admin/products', [
                'name' => 'Dilarang',
                'category_id' => $category->id,
                'price' => 1000,
                'stock' => 1,
            ])
            ->assertStatus(403);
    }

    public function test_dashboard_reports_totals(): void
    {
        $admin = $this->admin();
        $customer = User::factory()->create();

        Order::factory()->create([
            'user_id' => $customer->id,
            'status_order' => OrderStatus::COMPLETED,
            'status_payment' => PaymentStatus::PAID,
            'subtotal' => 200000,
            'final_amount' => 200000,
        ]);
        Order::factory()->create([
            'user_id' => $customer->id,
            'status_order' => OrderStatus::PENDING,
            'status_payment' => PaymentStatus::UNPAID,
        ]);

        Product::factory()->count(3)->create();

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/admin/dashboard-stats')
            ->assertStatus(200)
            ->assertJsonPath('data.products.total', 3)
            ->assertJsonPath('data.orders.total', 2)
            ->assertJsonPath('data.orders.pending', 1)
            ->assertJsonPath('data.orders.completed', 1)
            ->assertJsonPath('data.payments.unpaid', 1)
            ->assertJsonPath('data.payments.paid', 1)
            ->assertJsonPath('data.customers.total', 1)
            ->assertJsonPath('data.revenue.total', 200000)
            ->assertJsonStructure([
                'data' => [
                    'period' => ['from', 'to'],
                    'revenue' => ['total', 'today', 'this_month'],
                    'orders' => ['total', 'pending', 'processing', 'completed', 'cancelled'],
                    'payments' => ['unpaid', 'paid', 'refunded'],
                    'products' => ['total', 'active', 'out_of_stock'],
                    'customers' => ['total', 'new_this_month'],
                    'average_order_value',
                    'best_selling',
                    'top_categories',
                    'sales_by_date',
                    'recent_orders',
                ],
            ]);
    }

    public function test_sales_report_is_accessible(): void
    {
        $customer = User::factory()->create();
        Order::factory()->create([
            'user_id' => $customer->id,
            'status_payment' => PaymentStatus::PAID,
            'subtotal' => 300000,
            'final_amount' => 300000,
        ]);

        $this->actingAs($this->admin(), 'sanctum')
            ->getJson('/api/v1/admin/reports/sales')
            ->assertStatus(200)
            ->assertJsonPath('data.total_sales', 300000)
            ->assertJsonPath('data.total_orders', 1)
            ->assertJsonPath('data.average_order_value', 300000)
            ->assertJsonStructure([
                'data' => ['period' => ['from', 'to'], 'total_sales', 'total_orders',
                    'average_order_value', 'sales_by_date', 'best_selling'],
            ]);
    }

    public function test_audit_logs_are_listed_for_admin(): void
    {
        $admin = User::factory()->admin()->create([
            'email' => 'admin@parfumim.test',
            'password' => bcrypt('Admin#12345'),
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@parfumim.test',
            'password' => 'Admin#12345',
        ])->assertStatus(200);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/admin/audit-logs')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.action', 'auth.login')
            ->assertJsonStructure([
                'data' => [['id', 'action', 'entity_type']],
                'meta' => ['current_page', 'total'],
            ]);
    }
}
