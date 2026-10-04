<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $subtotal = fake()->randomFloat(2, 50000, 1000000);

        return [
            'order_code' => Order::generateCode(),
            'user_id' => User::factory(),
            'promotion_id' => null,
            'status_order' => OrderStatus::PENDING,
            'status_payment' => PaymentStatus::UNPAID,
            'subtotal' => $subtotal,
            'discount_amount' => 0,
            'final_amount' => $subtotal,
            'shipping_name' => fake()->name(),
            'shipping_phone' => fake()->numerify('08##########'),
            'shipping_address' => fake()->address(),
            'shipping_city' => fake()->city(),
            'shipping_postal_code' => fake()->numerify('#####'),
            'note' => null,
            'completed_at' => null,
            'cancelled_at' => null,
        ];
    }

    /**
     * Every order created through checkout also has a payment row, so the factory
     * mirrors that invariant for tests and seed data.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (Order $order): void {
            if ($order->payment()->exists()) {
                return;
            }

            Payment::query()->create([
                'order_id' => $order->id,
                'payment_method' => fake()->randomElement(PaymentMethod::values()),
                'payment_status' => $order->status_payment,
                'transaction_id' => null,
                'amount' => (float) $order->final_amount,
                'paid_at' => $order->status_payment === PaymentStatus::PAID ? now() : null,
                'refunded_at' => null,
            ]);
        });
    }

    public function status(OrderStatus $status): static
    {
        return $this->state(fn (array $attributes) => [
            'status_order' => $status,
            'completed_at' => $status === OrderStatus::COMPLETED ? now() : null,
            'cancelled_at' => $status === OrderStatus::CANCELLED ? now() : null,
        ]);
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status_payment' => PaymentStatus::PAID,
        ]);
    }
}
