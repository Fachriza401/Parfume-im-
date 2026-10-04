<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Serves the historical snapshot, so order history stays correct even after the
 * product name, price, or category changes (BR-04, BR-11).
 */
class OrderDetailResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'product_name_snapshot' => $this->product_name_snapshot,
            'price_snapshot' => (float) $this->price_snapshot,
            'category_name_snapshot' => $this->category_name_snapshot,
            'quantity' => (int) $this->quantity,
            'subtotal' => (float) $this->subtotal,
            'image_url' => $this->whenLoaded('product', fn () => $this->product?->image_url),
        ];
    }
}
