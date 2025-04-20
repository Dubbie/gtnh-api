<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CraftingMethod>
 */
class CraftingMethodFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->word() . ' ' . fake()->randomElement(['Assembler', 'Furnace', 'Centrifuge', 'Mixer']);
        return [
            'name' => $name,
            'description' => fake()->optional()->sentence(),
        ];
    }
}
