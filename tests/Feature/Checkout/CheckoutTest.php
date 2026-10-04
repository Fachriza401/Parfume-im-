<?php

namespace Tests\Feature\Checkout;

use App\Enums\DiscountType;
use App\Models\Cart;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'payments.banks.0.account_number' => '1234567890',
            'payments.banks.0.account_name' => 'Parfum IM',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function shipping(): array
    {
        return [
            'shipping_name' => 'Budi Santoso',
            'shipping_phone' => '08123456789',
            'shipping_address' => 'Jl. Melati No. 10',
            'shipping_city' => 'Bandung',
            'shipping_postal_code' => '40115',
            'payment_method' => 'bank_transfer',
            'payment_destination_code' => 'bca',
        ];
    }

    public function test_checkout_creates_order_details_payment_and_reduces_stock(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create(['name' => 'Floral']);
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'name' => 'Midnight Floral',
            'price' => 100000,
            'stock' => 10,
        ]);

        Cart::create(['user_id' => $user->id, 'product_id' => $product->id, 'quantity' => 2]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/checkout', $this->shipping())
            ->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status_order', 'pending')
            ->assertJsonPath('data.status_payment', 'unpaid')
            ->assertJsonPath('data.subtotal', 200000)
            ->assertJsonPath('data.final_amount', 200000)
            ->assertJsonPath('data.details.0.product_name_snapshot', 'Midnight Floral')
            ->assertJsonPath('data.details.0.price_snapshot', 100000)
            ->assertJsonPath('data.details.0.category_name_snapshot', 'Floral');
        $this->assertStringStartsWith('TRX-', Order::first()->payment->transaction_id);
        $this->assertSame('BCA', Order::first()->payment->meta['destination_name']);

        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('order_details', 1);
        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseCount('carts', 0);

        $this->assertSame(8, (int) $product->fresh()->stock);
        $this->assertSame(2, (int) $product->fresh()->sold_count);
    }

    public function test_checkout_records_the_selected_wallet_destination(): void
    {
        config([
            'payments.e_wallets.0.account_number' => '081234567890',
            'payments.e_wallets.0.account_name' => 'Parfum IM',
        ]);
        $user = User::factory()->create();
        $product = Product::factory()->create(['stock' => 5]);
        Cart::create(['user_id' => $user->id, 'product_id' => $product->id, 'quantity' => 1]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/checkout', array_replace($this->shipping(), [
                'payment_method' => 'e_wallet',
                'payment_destination_code' => 'gopay',
            ]))
            ->assertCreated()
            ->assertJsonPath('data.payment.destination_name', 'GoPay')
            ->assertJsonPath('data.payment.destination_account', '081234567890');
    }

    public function test_checkout_rejects_an_unconfigured_payment_destination(): void
    {
        config([
            'payments.banks.0.account_number' => '',
            'payments.banks.0.account_name' => '',
        ]);
        $user = User::factory()->create();
        $product = Product::factory()->create(['stock' => 5]);
        Cart::create(['user_id' => $user->id, 'product_id' => $product->id, 'quantity' => 1]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/checkout', $this->shipping())
            ->assertUnprocessable()
            ->assertJsonValidationErrors('payment_destination_code');
    }

    public function test_order_code_is_unique_and_prefixed(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['stock' => 5]);

        Cart::create(['user_id' => $user->id, 'product_id' => $product->id, 'quantity' => 1]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/checkout', $this->shipping())
            ->assertStatus(201);

        $order = Order::first();
        $this->assertStringStartsWith('PIM-', $order->order_code);
        $this->assertDatabaseHas('orders', ['order_code' => $order->order_code]);
    }

    public function test_checkout_fails_with_empty_cart(): void
    {
        $this->actingAs(User::factory()->create(), 'sanctum')
            ->postJson('/api/v1/checkout', $this->shipping())
            ->assertStatus(422)
            ->assertJsonPath('errors.code', 'EMPTY_CART');

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_admin_cannot_checkout(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/checkout', $this->shipping())
            ->assertStatus(403);

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_checkout_fails_when_stock_insufficient_and_rolls_back(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['stock' => 1, 'name' => 'Oud Noir']);

        Cart::create(['user_id' => $user->id, 'product_id' => $product->id, 'quantity' => 5]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/checkout', $this->shipping())
            ->assertStatus(422)
            ->assertJsonPath('errors.code', 'INSUFFICIENT_STOCK');

        // Nothing may be written when checkout fails.
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_details', 0);
        $this->assertDatabaseCount('payments', 0);
        $this->assertSame(1, (int) $product->fresh()->stock, 'Stock must be unchanged.');
        $this->assertDatabaseCount('carts', 1);
    }

    public function test_checkout_with_percentage_promotion(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['price' => 200000, 'stock' => 10]);

        Promotion::create([
            'code' => 'PARFUM10',
            'name' => 'Diskon 10%',
            'discount_type' => DiscountType::PERCENTAGE,
            'discount_value' => 10,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
            'is_active' => true,
        ]);

        Cart::create(['user_id' => $user->id, 'product_id' => $product->id, 'quantity' => 2]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/checkout', $this->shipping() + ['promotion_code' => 'PARFUM10'])
            ->assertStatus(201)
            ->assertJsonPath('data.subtotal', 400000)
            ->assertJsonPath('data.discount_amount', 40000)
            ->assertJsonPath('data.final_amount', 360000);

        $this->assertSame(1, (int) Promotion::where('code', 'PARFUM10')->first()->used_count);
    }

    public function test_percentage_promotion_respects_max_discount(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['price' => 1000000, 'stock' => 10]);

        Promotion::create([
            'code' => 'CAP50',
            'name' => 'Cap',
            'discount_type' => DiscountType::PERCENTAGE,
            'discount_value' => 50,
            'max_discount_amount' => 100000,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
            'is_active' => true,
        ]);

        Cart::create(['user_id' => $user->id, 'product_id' => $product->id, 'quantity' => 1]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/checkout', $this->shipping() + ['promotion_code' => 'CAP50'])
            ->assertStatus(201)
            ->assertJsonPath('data.discount_amount', 100000)
            ->assertJsonPath('data.final_amount', 900000);
    }

    public function test_fixed_promotion(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['price' => 300000, 'stock' => 10]);

        Promotion::create([
            'code' => 'HEMAT50K',
            'name' => 'Potongan',
            'discount_type' => DiscountType::FIXED,
            'discount_value' => 50000,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
            'is_active' => true,
        ]);

        Cart::create(['user_id' => $user->id, 'product_id' => $product->id, 'quantity' => 1]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/checkout', $this->shipping() + ['promotion_code' => 'HEMAT50K'])
            ->assertStatus(201)
            ->assertJsonPath('data.discount_amount', 50000)
            ->assertJsonPath('data.final_amount', 250000);
    }

    public function test_discount_never_exceeds_subtotal(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['price' => 40000, 'stock' => 10]);

        Promotion::create([
            'code' => 'HUGE',
            'name' => 'Fixed besar',
            'discount_type' => DiscountType::FIXED,
            'discount_value' => 500000,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
            'is_active' => true,
        ]);

        Cart::create(['user_id' => $user->id, 'product_id' => $product->id, 'quantity' => 1]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/checkout', $this->shipping() + ['promotion_code' => 'HUGE'])
            ->assertStatus(201)
            ->assertJsonPath('data.discount_amount', 40000)
            ->assertJsonPath('data.final_amount', 0);
    }

    public function test_checkout_rejects_expired_promotion(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['stock' => 5]);

        Promotion::create([
            'code' => 'EXPIRED',
            'name' => 'Kedaluwarsa',
            'discount_type' => DiscountType::PERCENTAGE,
            'discount_value' => 50,
            'starts_at' => now()->subDays(5),
            'ends_at' => now()->subDay(),
            'is_active' => true,
        ]);

        Cart::create(['user_id' => $user->id, 'product_id' => $product->id, 'quantity' => 1]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/checkout', $this->shipping() + ['promotion_code' => 'EXPIRED'])
            ->assertStatus(422)
            ->assertJsonPath('errors.code', 'INVALID_PROMOTION');

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_checkout_rejects_inactive_promotion(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['stock' => 5]);

        Promotion::create([
            'code' => 'OFF',
            'name' => 'Nonaktif',
            'discount_type' => DiscountType::PERCENTAGE,
            'discount_value' => 50,
            'is_active' => false,
        ]);

        Cart::create(['user_id' => $user->id, 'product_id' => $product->id, 'quantity' => 1]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/checkout', $this->shipping() + ['promotion_code' => 'OFF'])
            ->assertStatus(422)
            ->assertJsonPath('errors.code', 'INVALID_PROMOTION');
    }

    public function test_checkout_rejects_unknown_promotion(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['stock' => 5]);

        Cart::create(['user_id' => $user->id, 'product_id' => $product->id, 'quantity' => 1]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/checkout', $this->shipping() + ['promotion_code' => 'NOTEXIST'])
            ->assertStatus(422)
            ->assertJsonPath('errors.code', 'INVALID_PROMOTION');
    }

    public function test_checkout_fails_when_product_became_inactive(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['stock' => 5]);

        Cart::create(['user_id' => $user->id, 'product_id' => $product->id, 'quantity' => 1]);
        $product->update(['is_active' => false]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/checkout', $this->shipping())
            ->assertStatus(422)
            ->assertJsonPath('errors.code', 'PRODUCT_UNAVAILABLE');

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_promotion_below_minimum_order_is_rejected(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['price' => 50000, 'stock' => 5]);

        Promotion::create([
            'code' => 'MIN100',
            'name' => 'Min 100rb',
            'discount_type' => DiscountType::PERCENTAGE,
            'discount_value' => 10,
            'min_order_amount' => 100000,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
            'is_active' => true,
        ]);

        Cart::create(['user_id' => $user->id, 'product_id' => $product->id, 'quantity' => 1]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/checkout', $this->shipping() + ['promotion_code' => 'MIN100'])
            ->assertStatus(422)
            ->assertJsonPath('errors.code', 'INVALID_PROMOTION');
    }

    public function test_checkout_ignores_client_supplied_totals(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['price' => 100000, 'stock' => 10]);

        Cart::create(['user_id' => $user->id, 'product_id' => $product->id, 'quantity' => 1]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/checkout', $this->shipping() + [
                'subtotal' => 1,
                'final_amount' => 0,
                'discount_amount' => 999999,
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.subtotal', 100000)
            ->assertJsonPath('data.final_amount', 100000);
    }

    public function test_checkout_requires_authentication(): void
    {
        $this->postJson('/api/v1/checkout', $this->shipping())->assertStatus(401);
    }

    public function test_checkout_requires_shipping_fields(): void
    {
        $this->actingAs(User::factory()->create(), 'sanctum')
            ->postJson('/api/v1/checkout', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors([
                'shipping_name', 'shipping_phone', 'shipping_address',
                'shipping_city', 'shipping_postal_code', 'payment_method',
            ]);
    }

    public function test_checkout_creates_notification(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['stock' => 5]);

        Cart::create(['user_id' => $user->id, 'product_id' => $product->id, 'quantity' => 1]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/checkout', $this->shipping())
            ->assertStatus(201);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $user->id,
            'type' => 'order_created',
        ]);
    }

    public function test_payment_amount_matches_final_amount(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['price' => 250000, 'stock' => 5]);

        Cart::create(['user_id' => $user->id, 'product_id' => $product->id, 'quantity' => 1]);

        $this->actingAs($user, 'sanctum')->postJson('/api/v1/checkout', $this->shipping());

        $order = Order::first();

        $this->assertSame(
            (float) $order->final_amount,
            (float) $order->payment->amount
        );
    }
}
