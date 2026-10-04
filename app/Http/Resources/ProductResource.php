<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'category' => $this->whenLoaded('category', fn () => $this->category ? [
                'id' => $this->category->id,
                'name' => $this->category->name,
                'slug' => $this->category->slug,
            ] : null),
            'price' => (float) $this->price,
            'description' => $this->description,
            'image_url' => $this->image_url,
            'stock' => (int) $this->stock,
            'rating_avg' => (float) $this->rating_avg,
            'rating_count' => (int) $this->rating_count,
            'sold_count' => (int) $this->sold_count,
            'is_active' => (bool) $this->is_active,
            'is_favorite' => (bool) ($this->is_favorite ?? false),
            'can_rate' => (bool) ($this->can_rate ?? false),
            'created_at' => optional($this->created_at)->toISOString(),
        ];
    }
}
