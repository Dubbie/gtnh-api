<?php

namespace Database\Factories;

use App\Models\CraftingMethod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Recipe>
 */
class RecipeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->optional()->words(rand(2, 4), true),
            'crafting_method_id' => CraftingMethod::factory(),
            'eu_per_tick' => fake()->optional()->numberBetween(4, 512),
            'duration_ticks' => fake()->optional()->numberBetween(20, 1200),
            'notes' => fake()->optional()->sentence(),
            'is_default' => fake()->boolean(10),
        ];
    }
}
