<?php

namespace Tests\Feature\Rating;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Product;
use App\Models\Rating;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RatingTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_rate_product_from_completed_order(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status_order' => OrderStatus::COMPLETED,
        ]);
        $order->details()->create([
            'product_id' => $product->id,
            'product_name_snapshot' => $product->name,
            'price_snapshot' => 100000,
            'category_name_snapshot' => 'Floral',
            'quantity' => 1,
            'subtotal' => 100000,
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/products/'.$product->id.'/rating', [
                'rating' => 5,
                'review' => 'Wangi banget!',
            ])
            ->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.rating', 5);

        $this->assertDatabaseHas('ratings', [
            'user_id' => $user->id,
            'product_id' => $product->id,
            'rating' => 5,
        ]);
    }

    public function test_rating_updates_product_aggregate(): void
    {
        $product = Product::factory()->create(['rating_avg' => 0, 'rating_count' => 0]);

        $this->rate($product, 4);
        $this->assertSame(4.0, (float) $product->fresh()->rating_avg);
        $this->assertSame(1, (int) $product->fresh()->rating_count);

        $this->rate($product, 2);
        $this->assertSame(3.0, (float) $product->fresh()->rating_avg);
        $this->assertSame(2, (int) $product->fresh()->rating_count);
    }

    public function test_existing_rating_is_updated_instead_of_duplicated(): void
    {
        $product = Product::factory()->create();
        $user = $this->purchase($product);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/products/'.$product->id.'/rating', ['rating' => 1])
            ->assertStatus(201);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/products/'.$product->id.'/rating', ['rating' => 5])
            ->assertStatus(201);

        $this->assertDatabaseCount('ratings', 1);
        $this->assertSame(5, (int) Rating::first()->rating);
    }

    public function test_cannot_rate_without_purchase(): void
    {
        $product = Product::factory()->create();

        $this->actingAs(User::factory()->create(), 'sanctum')
            ->postJson('/api/v1/products/'.$product->id.'/rating', ['rating' => 5])
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_cannot_rate_when_order_not_completed(): void
    {
        $product = Product::factory()->create();
        $user = User::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status_order' => OrderStatus::PENDING,
        ]);
        $order->details()->create([
            'product_id' => $product->id,
            'product_name_snapshot' => $product->name,
            'price_snapshot' => 100000,
            'category_name_snapshot' => 'Floral',
            'quantity' => 1,
            'subtotal' => 100000,
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/products/'.$product->id.'/rating', ['rating' => 5])
            ->assertStatus(422);
    }

    public function test_rating_value_must_be_between_one_and_five(): void
    {
        $product = Product::factory()->create();
        $user = $this->purchase($product);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/products/'.$product->id.'/rating', ['rating' => 0])
            ->assertStatus(422)
            ->assertJsonValidationErrors('rating');

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/products/'.$product->id.'/rating', ['rating' => 6])
            ->assertStatus(422)
            ->assertJsonValidationErrors('rating');
    }

    public function test_rating_requires_authentication(): void
    {
        $product = Product::factory()->create();

        $this->postJson('/api/v1/products/'.$product->id.'/rating', ['rating' => 5])
            ->assertStatus(401);
    }

    public function test_public_review_list_is_available(): void
    {
        $product = Product::factory()->create();
        $user = $this->purchase($product);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/products/'.$product->id.'/rating', [
                'rating' => 5,
                'review' => 'Mantap',
            ]);

        $this->getJson('/api/v1/products/'.$product->id.'/reviews')
            ->assertStatus(200)
            ->assertJsonPath('data.0.rating', 5)
            ->assertJsonStructure([
                'data' => [['rating', 'review', 'user' => ['name']]],
                'meta' => ['current_page', 'total'],
            ]);
    }

    public function test_product_detail_marks_user_as_able_to_rate(): void
    {
        $product = Product::factory()->create();
        $user = $this->purchase($product);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/products/'.$product->id)
            ->assertStatus(200)
            ->assertJsonPath('data.can_rate', true);
    }

    private function rate(Product $product, int $stars): void
    {
        $user = $this->purchase($product);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/products/'.$product->id.'/rating', ['rating' => $stars])
            ->assertStatus(201);
    }

    private function purchase(Product $product): User
    {
        $user = User::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status_order' => OrderStatus::COMPLETED,
        ]);
        $order->details()->create([
            'product_id' => $product->id,
            'product_name_snapshot' => $product->name,
            'price_snapshot' => 100000,
            'category_name_snapshot' => 'Floral',
            'quantity' => 1,
            'subtotal' => 100000,
        ]);

        return $user;
    }
}
