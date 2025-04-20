<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserPreferredRecipe extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'output_item_id',
        'preferred_recipe_id',
    ];

    /**
     * Get the user who set this preference.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the item for which the preference is set.
     */
    public function outputItem(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'output_item_id');
    }

    /**
     * Get the recipe that is preferred.
     */
    public function preferredRecipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class, 'preferred_recipe_id');
    }
}
