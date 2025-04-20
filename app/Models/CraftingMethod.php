<?php

namespace App\Models;

use App\Traits\Sluggable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CraftingMethod extends Model
{
    use HasFactory, Sluggable;

    protected $fillable = [
        'name',
        'slug',
        'description',
    ];

    /**
     * Get the recipes that use this crafting method.
     */
    public function recipes(): HasMany
    {
        return $this->hasMany(Recipe::class);
    }
}
