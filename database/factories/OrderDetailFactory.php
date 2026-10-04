<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderDetail>
 */
class OrderDetailFactory extends Factory
{
    protected $model = OrderDetail::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $price = fake()->randomFloat(2, 50000, 1000000);
        $quantity = fake()->numberBetween(1, 3);

        return [
            'order_id' => Order::factory(),
            'product_id' => Product::factory(),
            'product_name_snapshot' => fake()->words(3, true),
            'price_snapshot' => $price,
            'category_name_snapshot' => fake()->randomElement(['Floral', 'Woody', 'Fresh']),
            'quantity' => $quantity,
            'subtotal' => $price * $quantity,
        ];
    }
}
