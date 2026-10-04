<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = Str::title(fake()->unique()->words(3, true));

        return [
            'category_id' => Category::factory(),
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(4)),
            'description' => fake()->paragraph(),
            'image_url' => 'https://placehold.co/600x600/121212/D4AF37?text='.urlencode($name),
            'price' => fake()->randomFloat(2, 50000, 1500000),
            'stock' => fake()->numberBetween(0, 100),
            'rating_avg' => 0,
            'rating_count' => 0,
            'sold_count' => 0,
            'is_active' => true,
        ];
    }

    public function outOfStock(): static
    {
        return $this->state(fn (array $attributes) => [
            'stock' => 0,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function sold(int $count): static
    {
        return $this->state(fn (array $attributes) => [
            'sold_count' => $count,
        ]);
    }
}
