<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Recipe extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'crafting_method_id',
        'notes',
        'duration_ticks',
        'eu_per_tick',
        'is_default'
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'output_quantity' => 'integer',
        'eu_per_tick' => 'integer',
        'duration_ticks' => 'integer',
    ];

    /**
     * Get the crafting method used by this recipe.
     */
    public function craftingMethod(): BelongsTo
    {
        return $this->belongsTo(CraftingMethod::class);
    }

    /**
     * Get the inputs required for this recipe. (No change needed here)
     */
    public function inputs(): HasMany
    {
        return $this->hasMany(RecipeInput::class);
    }

    /**
     * Get the user preferences that select this recipe. (No change needed here)
     */
    public function preferredByUsers(): HasMany
    {
        return $this->hasMany(UserPreferredRecipe::class, 'preferred_recipe_id');
    }

    /**
     * Get all output definitions for this recipe.
     */
    public function outputs(): HasMany
    {
        return $this->hasMany(RecipeOutput::class);
    }

    /**
     * Get the primary output definition (assuming only one).
     * Returns null if no primary output is marked.
     */
    public function primaryOutput(): HasOne
    {
        return $this->hasOne(RecipeOutput::class)->where('is_primary_output', true);
    }

    /**
     * Get the items produced by this recipe through the outputs table.
     * Allows access to pivot data (quantity, chance, is_primary_output).
     */
    public function outputItems(): BelongsToMany
    {
        return $this->belongsToMany(Item::class, 'recipe_outputs', 'recipe_id', 'item_id')
            ->withPivot('quantity', 'chance', 'is_primary_output')
            ->using(RecipeOutput::class); // Use our custom Pivot model
    }
}
