<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

class ItemResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => 'item',
            'attributes' => [
                'name' => $this->name,
                'slug' => $this->slug,
                'is_raw_material' => $this->is_raw_material,
                'description' => $this->description,
                'image_url' => $this->image_url,
                'created_at' => $this->created_at?->toIso8601String(),
                'updated_at' => $this->updated_at?->toIso8601String(),
            ],
            'links' => [
                'self' => route('items.show', ['item' => $this->id]),
            ]
        ];
    }
}
