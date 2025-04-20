<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class RecipeInput extends Pivot
{
    use HasFactory;

    public $timestamps = false;

    protected $table = 'recipe_inputs';

    protected $fillable = [
        'recipe_id',
        'input_item_id',
        'input_quantity',
    ];

    protected $casts = [
        'input_quantity' => 'integer',
    ];

    /**
     * Get the recipe this input belongs to.
     */
    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class);
    }

    /**
     * Get the item required as input.
     */
    public function inputItem(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'input_item_id');
    }
}
