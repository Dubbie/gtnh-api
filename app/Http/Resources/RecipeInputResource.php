<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RecipeInputResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'item' => new ItemResource($this->whenLoaded('inputItem')),
            'quantity' => $this->input_quantity,
        ];
    }
}
