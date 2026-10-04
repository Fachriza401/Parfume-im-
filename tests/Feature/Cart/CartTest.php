<?php

namespace Tests\Feature\Cart;

use App\Models\Cart;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CartTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        return User::factory()->create();
    }

    public function test_user_can_add_product_to_cart(): void
    {
        $user = $this->user();
        $product = Product::factory()->create(['price' => 100000, 'stock' => 10]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/cart', ['product_id' => $product->id, 'quantity' => 2])
            ->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.item.quantity', 2)
            ->assertJsonPath('data.item.subtotal', 200000)
            ->assertJsonPath('data.summary.subtotal', 200000);

        $this->assertDatabaseHas('carts', [
            'user_id' => $user->id,
            'product_id' => $product->id,
            'quantity' => 2,
        ]);
    }

    public function test_adding_same_product_merges_quantity(): void
    {
        $user = $this->user();
        $product = Product::factory()->create(['stock' => 10]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/cart', ['product_id' => $product->id, 'quantity' => 2])
            ->assertStatus(201);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/cart', ['product_id' => $product->id, 'quantity' => 3])
            ->assertStatus(201)
            ->assertJsonPath('data.item.quantity', 5);

        $this->assertDatabaseCount('carts', 1);
    }

    public function test_cannot_add_more_than_available_stock(): void
    {
        $user = $this->user();
        $product = Product::factory()->create(['stock' => 3, 'name' => 'Oud Noir']);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/cart', ['product_id' => $product->id, 'quantity' => 5])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('errors.code', 'INSUFFICIENT_STOCK');

        $this->assertDatabaseCount('carts', 0);
    }

    public function test_cannot_add_out_of_stock_product(): void
    {
        $user = $this->user();
        $product = Product::factory()->outOfStock()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/cart', ['product_id' => $product->id, 'quantity' => 1])
            ->assertStatus(422)
            ->assertJsonPath('errors.code', 'INSUFFICIENT_STOCK');
    }

    public function test_quantity_must_be_at_least_one(): void
    {
        $user = $this->user();
        $product = Product::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/cart', ['product_id' => $product->id, 'quantity' => 0])
            ->assertStatus(422)
            ->assertJsonValidationErrors('quantity');
    }

    public function test_nonexistent_product_is_rejected(): void
    {
        $this->actingAs($this->user(), 'sanctum')
            ->postJson('/api/v1/cart', ['product_id' => 9999, 'quantity' => 1])
            ->assertStatus(422)
            ->assertJsonValidationErrors('product_id');
    }

    public function test_user_can_update_quantity(): void
    {
        $user = $this->user();
        $product = Product::factory()->create(['price' => 50000, 'stock' => 10]);

        $cart = Cart::create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'quantity' => 1,
        ]);

        $this->actingAs($user, 'sanctum')
            ->patchJson('/api/v1/cart/'.$cart->id, ['quantity' => 4])
            ->assertStatus(200)
            ->assertJsonPath('data.item.quantity', 4)
            ->assertJsonPath('data.item.subtotal', 200000)
            ->assertJsonPath('data.items.0.quantity', 4)
            ->assertJsonPath('data.summary.subtotal', 200000);
    }

    public function test_update_beyond_stock_is_rejected(): void
    {
        $user = $this->user();
        $product = Product::factory()->create(['stock' => 2]);

        $cart = Cart::create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'quantity' => 1,
        ]);

        $this->actingAs($user, 'sanctum')
            ->patchJson('/api/v1/cart/'.$cart->id, ['quantity' => 10])
            ->assertStatus(422)
            ->assertJsonPath('errors.code', 'INSUFFICIENT_STOCK');
    }

    public function test_user_can_remove_cart_item(): void
    {
        $user = $this->user();
        $product = Product::factory()->create();

        $cart = Cart::create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'quantity' => 1,
        ]);

        $this->actingAs($user, 'sanctum')
            ->deleteJson('/api/v1/cart/'.$cart->id)
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(0, 'data.items')
            ->assertJsonPath('data.summary.total_items', 0);

        $this->assertDatabaseCount('carts', 0);
    }

    public function test_user_cannot_touch_another_users_cart_item(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $product = Product::factory()->create();

        $cart = Cart::create([
            'user_id' => $owner->id,
            'product_id' => $product->id,
            'quantity' => 1,
        ]);

        $this->actingAs($intruder, 'sanctum')
            ->patchJson('/api/v1/cart/'.$cart->id, ['quantity' => 5])
            ->assertStatus(404);

        $this->actingAs($intruder, 'sanctum')
            ->deleteJson('/api/v1/cart/'.$cart->id)
            ->assertStatus(404);
    }

    public function test_cart_requires_authentication(): void
    {
        $this->getJson('/api/v1/cart')->assertStatus(401);
    }

    public function test_empty_cart_returns_empty_summary(): void
    {
        $this->actingAs($this->user(), 'sanctum')
            ->getJson('/api/v1/cart')
            ->assertStatus(200)
            ->assertJsonCount(0, 'data.items')
            ->assertJsonPath('data.summary.subtotal', 0)
            ->assertJsonPath('data.summary.final_amount', 0);
    }

    public function test_cart_summary_totals_multiple_items(): void
    {
        $user = $this->user();
        $a = Product::factory()->create(['price' => 100000, 'stock' => 10]);
        $b = Product::factory()->create(['price' => 50000, 'stock' => 10]);

        Cart::create(['user_id' => $user->id, 'product_id' => $a->id, 'quantity' => 2]);
        Cart::create(['user_id' => $user->id, 'product_id' => $b->id, 'quantity' => 1]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/cart')
            ->assertStatus(200)
            ->assertJsonPath('data.summary.subtotal', 250000)
            ->assertJsonPath('data.summary.total_items', 3);
    }
}
