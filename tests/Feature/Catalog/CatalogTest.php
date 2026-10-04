<?php

namespace Tests\Feature\Catalog;

use App\Models\Category;
use App\Models\Favorite;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_list_products(): void
    {
        Product::factory()->count(5)->create();

        $this->getJson('/api/v1/products')
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(5, 'data')
            ->assertJsonStructure([
                'data' => [
                    ['id', 'name', 'slug', 'price', 'stock', 'image_url',
                        'rating_avg', 'rating_count', 'category'],
                ],
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
            ]);
    }

    public function test_products_are_paginated(): void
    {
        Product::factory()->count(20)->create();

        $response = $this->getJson('/api/v1/products?per_page=5&page=2');

        $response->assertStatus(200)
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonPath('meta.per_page', 5)
            ->assertJsonPath('meta.total', 20);
    }

    public function test_search_filters_by_name(): void
    {
        Product::factory()->create(['name' => 'Midnight Floral Eau']);
        Product::factory()->create(['name' => 'Cedar Woods']);

        $this->getJson('/api/v1/products?search=Midnight')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Midnight Floral Eau');
    }

    public function test_search_returns_empty_list_when_no_match(): void
    {
        Product::factory()->create(['name' => 'Midnight Floral']);

        $this->getJson('/api/v1/products?search=TidakAda')
            ->assertStatus(200)
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.total', 0);
    }

    public function test_filter_by_category_id(): void
    {
        $floral = Category::factory()->create();
        $woody = Category::factory()->create();

        Product::factory()->create(['category_id' => $floral->id]);
        Product::factory()->create(['category_id' => $woody->id]);

        $this->getJson('/api/v1/products?category='.$floral->id)
            ->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_filter_by_category_slug(): void
    {
        $category = Category::factory()->create(['slug' => 'floral']);
        Product::factory()->create(['category_id' => $category->id]);
        Product::factory()->count(2)->create();

        $this->getJson('/api/v1/products?category=floral')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_filter_by_price_range(): void
    {
        Product::factory()->create(['price' => 100000]);
        Product::factory()->create(['price' => 500000]);

        $this->getJson('/api/v1/products?min_price=200000&max_price=600000')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.price', 500000);
    }

    public function test_sort_by_price_descending(): void
    {
        Product::factory()->create(['price' => 100000]);
        Product::factory()->create(['price' => 900000]);
        Product::factory()->create(['price' => 500000]);

        $response = $this->getJson('/api/v1/products?sort=price_desc');

        $response->assertStatus(200)->assertJsonPath('data.0.price', 900000);
    }

    public function test_sort_by_price_ascending(): void
    {
        Product::factory()->create(['price' => 100000]);
        Product::factory()->create(['price' => 900000]);

        $this->getJson('/api/v1/products?sort=price_asc')
            ->assertStatus(200)
            ->assertJsonPath('data.0.price', 100000);
    }

    public function test_popular_sort_uses_sold_count(): void
    {
        Product::factory()->create(['sold_count' => 5]);
        Product::factory()->create(['sold_count' => 500]);

        $this->getJson('/api/v1/products?sort=popular')
            ->assertStatus(200)
            ->assertJsonPath('data.0.sold_count', 500);
    }

    public function test_inactive_products_are_hidden(): void
    {
        Product::factory()->inactive()->create();
        Product::factory()->create();

        $this->getJson('/api/v1/products')->assertJsonCount(1, 'data');
    }

    public function test_soft_deleted_products_are_hidden(): void
    {
        $deleted = Product::factory()->create();
        $deleted->delete();

        Product::factory()->create();

        $this->getJson('/api/v1/products')->assertJsonCount(1, 'data');
    }

    public function test_invalid_sort_is_rejected(): void
    {
        $this->getJson('/api/v1/products?sort=; DROP TABLE users; --')
            ->assertStatus(422)
            ->assertJsonValidationErrors('sort');
    }

    public function test_guest_can_view_product_detail(): void
    {
        $product = Product::factory()->create(['name' => 'Oud Noir']);

        $this->getJson('/api/v1/products/'.$product->id)
            ->assertStatus(200)
            ->assertJsonPath('data.name', 'Oud Noir')
            ->assertJsonPath('data.id', $product->id)
            ->assertJsonStructure(['data' => ['id', 'name', 'price', 'stock', 'description']]);
    }

    public function test_unknown_product_returns_404(): void
    {
        $this->getJson('/api/v1/products/9999')
            ->assertStatus(404)
            ->assertJsonPath('success', false);
    }

    public function test_categories_are_listed_with_product_count(): void
    {
        $category = Category::factory()->create(['name' => 'Floral']);
        Product::factory()->count(3)->create(['category_id' => $category->id]);

        $this->getJson('/api/v1/categories')
            ->assertStatus(200)
            ->assertJsonPath('data.0.name', 'Floral')
            ->assertJsonPath('data.0.product_count', 3);
    }

    public function test_favorite_flag_is_only_set_for_the_owning_user(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/products')
            ->assertStatus(200)
            ->assertJsonPath('data.0.is_favorite', false);

        Favorite::create(['user_id' => $user->id, 'product_id' => $product->id]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/products')
            ->assertStatus(200)
            ->assertJsonPath('data.0.is_favorite', true);
    }
}
