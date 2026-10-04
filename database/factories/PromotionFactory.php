<?php

namespace Database\Factories;

use App\Enums\DiscountType;
use App\Models\Promotion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Promotion>
 */
class PromotionFactory extends Factory
{
    protected $model = Promotion::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->bothify('PROMO??####')),
            'name' => fake()->words(2, true),
            'description' => fake()->sentence(),
            'discount_type' => DiscountType::PERCENTAGE,
            'discount_value' => 10,
            'max_discount_amount' => null,
            'min_order_amount' => 0,
            'usage_limit' => null,
            'used_count' => 0,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addMonth(),
            'is_active' => true,
        ];
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'starts_at' => now()->subMonths(2),
            'ends_at' => now()->subDay(),
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function fixed(float $value): static
    {
        return $this->state(fn (array $attributes) => [
            'discount_type' => DiscountType::FIXED,
            'discount_value' => $value,
        ]);
    }
}
