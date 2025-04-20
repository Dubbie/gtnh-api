<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Item>
 */
class ItemFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->words(rand(1, 3), true); // Unique name
        return [
            'name' => ucwords($name), // Capitalize words
            // Slug is handled by the Sluggable trait, no need to define here usually
            'is_raw_material' => fake()->boolean(25), // 25% chance of being raw
            'description' => fake()->optional()->sentence(), // Optional description
            'image_url' => fake()->optional()->imageUrl(60, 60, 'technics', true), // Optional image
        ];
    }

    // State for specifically creating a raw material
    public function asRaw(): static
    {
        return $this->state(fn(array $attributes) => [
            'is_raw_material' => true,
        ]);
    }

    // State for specifically creating a craftable item
    public function asCraftable(): static
    {
        return $this->state(fn(array $attributes) => [
            'is_raw_material' => false,
        ]);
    }
}
