<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'title' => $this->title,
            'body' => $this->body,
            'data' => $this->data ?? [],
            'is_read' => (bool) $this->is_read,
            'read_at' => optional($this->read_at)->toISOString(),
            'created_at' => optional($this->created_at)->toISOString(),
        ];
    }
}
