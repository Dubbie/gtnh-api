<?php

namespace App\Models;

use App\Traits\Sluggable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Item extends Model
{
    use HasFactory, Sluggable;

    protected $fillable = [
        'name',
        'slug',
        'is_raw_material',
        'description',
        'image_url',
    ];

    protected $casts = [
        'is_raw_material' => 'boolean',
    ];

    /**
     * Scope a query to only include items that have multiple associated recipes.
     *
     * @param Builder $query
     * @return void
     */
    public function scopeHasMultipleRecipes(Builder $query): void
    {
        $query->select('items.*') // Select item columns
            ->join('recipe_outputs', 'items.id', '=', 'recipe_outputs.item_id') // Join outputs
            ->join('recipes', 'recipe_outputs.recipe_id', '=', 'recipes.id') // Join recipes
            ->groupBy('items.id') // Group by item
            ->havingRaw('COUNT(DISTINCT recipes.id) >= 2'); // Count distinct recipes per item
    }

    /**
     * Get the recipe inputs where this item is used.
     */
    public function recipeInputs(): HasMany
    {
        return $this->hasMany(RecipeInput::class, 'input_item_id');
    }

    /**
     * Get the user preferences set for this item.
     */
    public function userPreferences(): HasMany
    {
        return $this->hasMany(UserPreferredRecipe::class, 'output_item_id');
    }

    /**
     * Get the specific output entries where this item is produced.
     */
    public function recipeOutputs(): HasMany
    {
        return $this->hasMany(RecipeOutput::class, 'item_id');
    }


    /**
     * Get the recipes where this item is listed as an output.
     */
    public function recipesThatProduce(): HasManyThrough
    {
        // Syntax: hasManyThrough(RelatedModel, ThroughModel, firstKeyOnThroughModel, secondKeyOnThroughModel, localKeyOnThisModel, localKeyOnThroughModel)
        return $this->hasManyThrough(
            Recipe::class,          // Related Model (Recipe)
            RecipeOutput::class,    // Through Model (RecipeOutput)
            'item_id',              // Foreign key on through model (RecipeOutput -> Item)
            'id',                   // Foreign key on related model (Recipe -> RecipeOutput)
            'id',                   // Local key on this model (Item)
            'recipe_id'             // Local key on through model (RecipeOutput)
        );
    }
}
