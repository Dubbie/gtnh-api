<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class RecipeOutput extends Pivot
{
    use HasFactory;

    // Explicitly define the table name as it doesn't follow pivot naming convention
    protected $table = 'recipe_outputs';

    // Disable timestamps if you didn't add them in the migration
    public $timestamps = false;

    // Allow mass assignment
    protected $fillable = [
        'recipe_id',
        'item_id',
        'quantity',
        'chance',
        'is_primary_output',
    ];

    // Define casts for data types
    protected $casts = [
        'quantity' => 'integer',
        'chance' => 'integer',
        'is_primary_output' => 'boolean',
    ];

    /**
     * Get the recipe this output belongs to.
     */
    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class);
    }

    /**
     * Get the item produced.
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
