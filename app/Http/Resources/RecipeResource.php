<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RecipeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => 'recipe',
            'attributes' => [
                'name' => $this->name,
                'eu_per_tick' => $this->eu_per_tick,
                'duration_ticks' => $this->duration_ticks,
                'duration_seconds' => $this->duration_ticks ? round($this->duration_ticks / 20, 2) : null,
                'notes' => $this->notes,
                'is_default' => $this->is_default,
                'created_at' => $this->created_at?->toIso8601String(),
                'updated_at' => $this->updated_at?->toIso8601String(),
            ],
            'relationships' => [
                'crafting_method' => new CraftingMethodResource($this->whenLoaded('craftingMethod')),
                'inputs' => RecipeInputResource::collection($this->whenLoaded('inputs')),
                'outputs' => RecipeOutputResource::collection($this->whenLoaded('outputs')),
                'primary_output_item' => new ItemResource($this->whenLoaded('primaryOutput', function () {
                    return $this->primaryOutput->relationLoaded('item') ? $this->primaryOutput->item : null;
                })),
            ],
            'links' => [
                'self' => route('recipes.show', $this->id),
            ],
        ];
    }
}
