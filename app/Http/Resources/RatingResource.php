<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RatingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'rating' => (int) $this->rating,
            'review' => $this->review,
            'user' => $this->relationLoaded('user') ? [
                'id' => $this->user->id,
                'name' => $this->user->name,
            ] : null,
            'created_at' => optional($this->created_at)->toISOString(),
        ];
    }
}
