<?php

namespace Tests\Feature\Favorite;

use App\Models\Favorite;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FavoriteTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_favorite_product(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/favorites', ['product_id' => $product->id])
            ->assertStatus(201)
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('favorites', [
            'user_id' => $user->id,
            'product_id' => $product->id,
        ]);
    }

    public function test_favoriting_twice_is_idempotent(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/favorites', ['product_id' => $product->id])
            ->assertStatus(201);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/favorites', ['product_id' => $product->id])
            ->assertStatus(201);

        $this->assertDatabaseCount('favorites', 1);
    }

    public function test_cannot_favorite_inactive_product(): void
    {
        $product = Product::factory()->inactive()->create();

        $this->actingAs(User::factory()->create(), 'sanctum')
            ->postJson('/api/v1/favorites', ['product_id' => $product->id])
            ->assertStatus(422);
    }

    public function test_user_can_list_favorites(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();

        Favorite::create(['user_id' => $user->id, 'product_id' => $product->id]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/favorites')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $product->id)
            ->assertJsonPath('data.0.is_favorite', true);
    }

    public function test_favorites_list_is_scoped_to_the_user(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $mine = Product::factory()->create();
        $theirs = Product::factory()->create();

        Favorite::create(['user_id' => $user->id, 'product_id' => $mine->id]);
        Favorite::create(['user_id' => $other->id, 'product_id' => $theirs->id]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/favorites')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $mine->id);
    }

    public function test_user_can_remove_favorite(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();

        Favorite::create(['user_id' => $user->id, 'product_id' => $product->id]);

        $this->actingAs($user, 'sanctum')
            ->deleteJson('/api/v1/favorites/'.$product->id)
            ->assertStatus(200);

        $this->assertDatabaseCount('favorites', 0);
    }

    public function test_cannot_remove_favorite_that_does_not_exist(): void
    {
        $product = Product::factory()->create();

        $this->actingAs(User::factory()->create(), 'sanctum')
            ->deleteJson('/api/v1/favorites/'.$product->id)
            ->assertStatus(404);
    }

    public function test_favorites_require_authentication(): void
    {
        $this->getJson('/api/v1/favorites')->assertStatus(401);
    }
}
