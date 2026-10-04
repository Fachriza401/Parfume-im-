<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    protected $model = Category::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->randomElement([
            'Floral', 'Woody', 'Fresh', 'Oriental', 'Citrus', 'Amber', 'Musk', 'Vanilla',
        ]).' '.fake()->unique()->numberBetween(1, 999999);

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(4)),
            'image' => 'https://placehold.co/400x400/121212/D4AF37?text='.urlencode($name),
            'description' => fake()->sentence(),
            'is_active' => true,
        ];
    }
}
