<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_code' => $this->order_code,
            'status_order' => $this->status_order->value,
            'status_order_label' => $this->status_order->label(),
            'status_payment' => $this->status_payment->value,
            'status_payment_label' => $this->status_payment->label(),
            'subtotal' => (float) $this->subtotal,
            'discount_amount' => (float) $this->discount_amount,
            'final_amount' => (float) $this->final_amount,
            'promotion' => $this->whenLoaded('promotion', fn () => $this->promotion ? [
                'id' => $this->promotion->id,
                'code' => $this->promotion->code,
                'name' => $this->promotion->name,
            ] : null),
            'shipping' => [
                'name' => $this->shipping_name,
                'phone' => $this->shipping_phone,
                'address' => $this->shipping_address,
                'city' => $this->shipping_city,
                'postal_code' => $this->shipping_postal_code,
            ],
            'note' => $this->note,
            'total_quantity' => $this->when(
                isset($this->details_count),
                (int) $this->details_count
            ),
            'details_count' => $this->when(
                isset($this->details_count),
                (int) $this->details_count
            ),
            'details' => OrderDetailResource::collection($this->whenLoaded('details')),
            'payment' => new PaymentResource($this->whenLoaded('payment')),
            'customer' => $this->when(
                $this->relationLoaded('user'),
                fn () => $this->user ? [
                    'id' => $this->user->id,
                    'name' => $this->user->name,
                    'email' => $this->user->email,
                    'phone' => $this->user->phone,
                ] : null
            ),
            'created_at' => optional($this->created_at)->toISOString(),
            'completed_at' => optional($this->completed_at)->toISOString(),
            'cancelled_at' => optional($this->cancelled_at)->toISOString(),
        ];
    }
}
