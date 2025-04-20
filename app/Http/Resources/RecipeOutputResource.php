<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RecipeOutputResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'item' => new ItemResource($this->whenLoaded('item')), // Load nested item info
            'quantity' => $this->quantity,
            'chance' => $this->chance / 100, // Convert 10000 scale to percentage e.g. 100.00
            'chance_raw' => $this->chance, // Optionally include raw value
            'is_primary' => $this->is_primary_output,
        ];
    }
}
