<?php

namespace Database\Factories;

use App\Models\Item;
use App\Models\Recipe;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\UserPreferredRecipe>
 */
class UserPreferredRecipeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $userId = User::factory();
        $itemId = Item::factory()->asCraftable();
        $recipeId = Recipe::factory();

        return [
            'user_id' => $userId,
            'output_item_id' => $itemId,
            'preferred_recipe_id' => $recipeId,
        ];
    }
}
