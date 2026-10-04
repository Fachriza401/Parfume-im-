<?php

namespace Tests\Feature\Order;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OrderTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    public function test_user_can_list_only_their_own_orders(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        Order::factory()->create(['user_id' => $user->id]);
        Order::factory()->create(['user_id' => $other->id]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/orders')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_user_can_view_own_order_detail(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/orders/'.$order->id)
            ->assertStatus(200)
            ->assertJsonPath('data.id', $order->id)
            ->assertJsonStructure(['data' => ['order_code', 'status_order', 'status_payment', 'details']]);
    }

    public function test_user_cannot_view_another_users_order(): void
    {
        $order = Order::factory()->create(['user_id' => User::factory()->create()->id]);

        $this->actingAs(User::factory()->create(), 'sanctum')
            ->getJson('/api/v1/orders/'.$order->id)
            ->assertStatus(404);
    }

    public function test_orders_can_be_filtered_by_status(): void
    {
        $user = User::factory()->create();
        Order::factory()->create(['user_id' => $user->id, 'status_order' => OrderStatus::PENDING]);
        Order::factory()->create(['user_id' => $user->id, 'status_order' => OrderStatus::COMPLETED]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/orders?status=completed')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_order_history_paginates_and_only_loads_item_previews(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $user->id]);

        foreach (range(1, 5) as $number) {
            $product = Product::factory()->create();
            $order->details()->create([
                'product_id' => $product->id,
                'product_name_snapshot' => $product->name,
                'price_snapshot' => $product->price,
                'category_name_snapshot' => $product->category->name,
                'quantity' => 1,
                'subtotal' => $product->price,
            ]);
        }

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/orders?per_page=1')
            ->assertOk()
            ->assertJsonPath('meta.per_page', 1)
            ->assertJsonPath('data.0.details_count', 5)
            ->assertJsonCount(3, 'data.0.details');
    }

    public function test_orders_require_authentication(): void
    {
        $this->getJson('/api/v1/orders')->assertStatus(401);
    }

    public function test_user_can_cancel_own_pending_order_and_stock_is_restored(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['stock' => 4]);
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status_order' => OrderStatus::PENDING,
        ]);
        $order->details()->create([
            'product_id' => $product->id,
            'product_name_snapshot' => $product->name,
            'price_snapshot' => 100000,
            'category_name_snapshot' => $product->category->name,
            'quantity' => 2,
            'subtotal' => 200000,
        ]);
        $product->increment('sold_count', 2);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/orders/'.$order->id.'/cancel')
            ->assertStatus(200)
            ->assertJsonPath('data.status_order', 'cancelled');

        $this->assertSame(6, (int) $product->fresh()->stock);
        $this->assertSame(0, (int) $product->fresh()->sold_count);
        $this->assertNotNull($order->fresh()->cancelled_at);
    }

    public function test_user_cannot_cancel_another_users_order(): void
    {
        $order = Order::factory()->create([
            'user_id' => User::factory()->create()->id,
            'status_order' => OrderStatus::PENDING,
        ]);

        $this->actingAs(User::factory()->create(), 'sanctum')
            ->postJson('/api/v1/orders/'.$order->id.'/cancel')
            ->assertStatus(404);
    }

    public function test_processing_order_cannot_be_cancelled_by_owner(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status_order' => OrderStatus::PROCESSING,
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/orders/'.$order->id.'/cancel')
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_admin_can_list_all_orders(): void
    {
        Order::factory()->count(3)->create();

        $this->actingAs($this->admin(), 'sanctum')
            ->getJson('/api/v1/admin/orders')
            ->assertStatus(200)
            ->assertJsonCount(3, 'data');
    }

    public function test_admin_can_advance_order_to_processing(): void
    {
        $order = Order::factory()->create(['status_order' => OrderStatus::PENDING]);

        $this->actingAs($this->admin(), 'sanctum')
            ->patchJson('/api/v1/admin/orders/'.$order->id.'/status', ['status_order' => 'processing'])
            ->assertStatus(200)
            ->assertJsonPath('data.status_order', 'processing');

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status_order' => OrderStatus::PROCESSING->value,
        ]);
    }

    public function test_admin_can_complete_a_processing_order(): void
    {
        $order = Order::factory()->create(['status_order' => OrderStatus::PROCESSING]);

        $this->actingAs($this->admin(), 'sanctum')
            ->patchJson('/api/v1/admin/orders/'.$order->id.'/status', ['status_order' => 'completed'])
            ->assertStatus(200)
            ->assertJsonPath('data.status_order', 'completed');

        $this->assertNotNull($order->fresh()->completed_at);
    }

    public function test_admin_can_cancel_a_pending_order_and_stock_is_restored(): void
    {
        $product = Product::factory()->create(['stock' => 4]);
        $order = Order::factory()->create(['status_order' => OrderStatus::PENDING]);
        $order->details()->create([
            'product_id' => $product->id,
            'product_name_snapshot' => $product->name,
            'price_snapshot' => 100000,
            'category_name_snapshot' => $product->category->name,
            'quantity' => 2,
            'subtotal' => 200000,
        ]);
        $product->increment('sold_count', 2);

        $this->actingAs($this->admin(), 'sanctum')
            ->patchJson('/api/v1/admin/orders/'.$order->id.'/status', ['status_order' => 'cancelled'])
            ->assertStatus(200)
            ->assertJsonPath('data.status_order', 'cancelled');

        $this->assertSame(6, (int) $product->fresh()->stock);
        $this->assertSame(0, (int) $product->fresh()->sold_count);
        $this->assertNotNull($order->fresh()->cancelled_at);
    }

    public function test_processing_order_cannot_be_cancelled(): void
    {
        $order = Order::factory()->create(['status_order' => OrderStatus::PROCESSING]);

        $this->actingAs($this->admin(), 'sanctum')
            ->patchJson('/api/v1/admin/orders/'.$order->id.'/status', ['status_order' => 'cancelled'])
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status_order' => OrderStatus::PROCESSING->value,
        ]);
    }

    public function test_completed_order_cannot_transition_again(): void
    {
        $order = Order::factory()->create(['status_order' => OrderStatus::COMPLETED]);

        $this->actingAs($this->admin(), 'sanctum')
            ->patchJson('/api/v1/admin/orders/'.$order->id.'/status', ['status_order' => 'processing'])
            ->assertStatus(422);
    }

    public function test_cancelled_order_cannot_transition_again(): void
    {
        $order = Order::factory()->create(['status_order' => OrderStatus::CANCELLED]);

        $this->actingAs($this->admin(), 'sanctum')
            ->patchJson('/api/v1/admin/orders/'.$order->id.'/status', ['status_order' => 'processing'])
            ->assertStatus(422);
    }

    public function test_invalid_status_value_is_rejected(): void
    {
        $order = Order::factory()->create();

        $this->actingAs($this->admin(), 'sanctum')
            ->patchJson('/api/v1/admin/orders/'.$order->id.'/status', ['status_order' => 'shipped'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('status_order');
    }

    public function test_status_update_requires_validation(): void
    {
        $order = Order::factory()->create();

        $this->actingAs($this->admin(), 'sanctum')
            ->patchJson('/api/v1/admin/orders/'.$order->id.'/status', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('status_order');
    }

    public function test_admin_can_mark_payment_as_paid(): void
    {
        $order = Order::factory()->create([
            'status_order' => OrderStatus::PENDING,
            'status_payment' => PaymentStatus::UNPAID,
        ]);
        $order->payment->update(['payment_method' => PaymentMethod::COD]);

        $this->actingAs($this->admin(), 'sanctum')
            ->patchJson('/api/v1/admin/orders/'.$order->id.'/payment', [
                'payment_status' => 'paid',
                'transaction_id' => 'TRX-12345',
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.status_payment', 'paid')
            ->assertJsonPath('data.status_order', 'processing');

        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'payment_status' => PaymentStatus::PAID->value,
            'transaction_id' => 'TRX-12345',
        ]);
        $this->assertNotNull($order->fresh()->payment->paid_at);
    }

    public function test_paid_cannot_revert_to_unpaid(): void
    {
        $order = Order::factory()->create(['status_payment' => PaymentStatus::PAID]);

        $this->actingAs($this->admin(), 'sanctum')
            ->patchJson('/api/v1/admin/orders/'.$order->id.'/payment', ['payment_status' => 'unpaid'])
            ->assertStatus(422);
    }

    public function test_transaction_id_must_be_unique(): void
    {
        $first = Order::factory()->create(['status_payment' => PaymentStatus::PAID]);
        $first->payment->update(['payment_method' => PaymentMethod::COD]);
        $first->payment->update(['transaction_id' => 'TRX-DUPE']);

        $second = Order::factory()->create(['status_payment' => PaymentStatus::UNPAID]);
        $second->payment->update(['payment_method' => PaymentMethod::COD]);

        $this->actingAs($this->admin(), 'sanctum')
            ->patchJson('/api/v1/admin/orders/'.$second->id.'/payment', [
                'payment_status' => 'paid',
                'transaction_id' => 'TRX-DUPE',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('transaction_id');
    }

    public function test_customer_can_upload_receipt_and_admin_can_review_it(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $user->id]);
        $order->payment->update(['payment_method' => PaymentMethod::BANK_TRANSFER]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/orders/'.$order->id.'/payment-receipt', [
                'receipt' => UploadedFile::fake()->image('receipt.jpg'),
            ])
            ->assertOk()
            ->assertJsonPath('data.receipt_uploaded', true);

        $this->assertTrue((bool) $order->fresh()->payment->meta['receipt_path']);

        $this->actingAs($this->admin(), 'sanctum')
            ->get('/api/v1/admin/orders/'.$order->id.'/payment-receipt')
            ->assertOk();

        $this->actingAs($this->admin(), 'sanctum')
            ->patchJson('/api/v1/admin/orders/'.$order->id.'/payment', [
                'payment_status' => PaymentStatus::PAID->value,
            ])
            ->assertOk()
            ->assertJsonPath('data.status_payment', 'paid')
            ->assertJsonPath('data.status_order', 'processing');
    }

    public function test_admin_cannot_verify_bank_transfer_without_receipt(): void
    {
        $order = Order::factory()->create(['status_payment' => PaymentStatus::UNPAID]);
        $order->payment->update(['payment_method' => PaymentMethod::BANK_TRANSFER]);

        $this->actingAs($this->admin(), 'sanctum')
            ->patchJson('/api/v1/admin/orders/'.$order->id.'/payment', [
                'payment_status' => PaymentStatus::PAID->value,
            ])
            ->assertStatus(422)
            ->assertJsonPath('errors.code', 'PAYMENT_RECEIPT_REQUIRED');
    }

    public function test_customer_cannot_update_order_status(): void
    {
        $order = Order::factory()->create(['user_id' => User::factory()->create()->id]);

        $this->actingAs(User::factory()->create(), 'sanctum')
            ->patchJson('/api/v1/admin/orders/'.$order->id.'/status', ['status_order' => 'processing'])
            ->assertStatus(403);
    }

    public function test_customer_cannot_update_payment(): void
    {
        $order = Order::factory()->create(['user_id' => User::factory()->create()->id]);

        $this->actingAs(User::factory()->create(), 'sanctum')
            ->patchJson('/api/v1/admin/orders/'.$order->id.'/payment', ['payment_status' => 'paid'])
            ->assertStatus(403);
    }

    public function test_order_detail_keeps_snapshot_when_product_changes(): void
    {
        $product = Product::factory()->create(['name' => 'Nama Lama', 'price' => 100000]);
        $order = Order::factory()->create(['status_order' => OrderStatus::PENDING]);
        $order->details()->create([
            'product_id' => $product->id,
            'product_name_snapshot' => 'Nama Lama',
            'price_snapshot' => 100000,
            'category_name_snapshot' => 'Floral',
            'quantity' => 1,
            'subtotal' => 100000,
        ]);

        $product->update(['name' => 'Nama Baru', 'price' => 999000]);

        $response = $this->actingAs(User::factory()->create(), 'sanctum')
            ->getJson('/api/v1/orders/'.$order->id);

        // Snapshot belongs to the order owner, so authenticate as that owner.
        $this->actingAs($order->user, 'sanctum')
            ->getJson('/api/v1/orders/'.$order->id)
            ->assertStatus(200)
            ->assertJsonPath('data.details.0.product_name_snapshot', 'Nama Lama')
            ->assertJsonPath('data.details.0.price_snapshot', 100000);

        $response->assertStatus(404);
    }
}
