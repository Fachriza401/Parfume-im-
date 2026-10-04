<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CartResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $price = (float) $this->product->price;
        $quantity = (int) $this->quantity;

        return [
            'id' => $this->id,
            'product_id' => (int) $this->product_id,
            'quantity' => $quantity,
            'subtotal' => $price * $quantity,
            'product' => [
                'id' => (int) $this->product->id,
                'name' => $this->product->name,
                'slug' => $this->product->slug,
                'image_url' => $this->product->image_url,
                'price' => $price,
                'stock' => (int) $this->product->stock,
                'category' => $this->product->relationLoaded('category') && $this->product->category
                    ? ['id' => $this->product->category->id, 'name' => $this->product->category->name]
                    : null,
            ],
            'updated_at' => optional($this->updated_at)->toISOString(),
        ];
    }
}
