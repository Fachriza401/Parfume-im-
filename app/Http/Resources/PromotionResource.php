<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PromotionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
            'image_url' => $this->image_url,
            'discount_type' => $this->discount_type->value,
            'discount_value' => (float) $this->discount_value,
            'max_discount_amount' => $this->max_discount_amount !== null
                ? (float) $this->max_discount_amount
                : null,
            'min_order_amount' => (float) $this->min_order_amount,
            'usage_limit' => $this->usage_limit,
            'used_count' => (int) $this->used_count,
            'starts_at' => optional($this->starts_at)->toISOString(),
            'ends_at' => optional($this->ends_at)->toISOString(),
            'is_active' => (bool) $this->is_active,
        ];
    }
}
