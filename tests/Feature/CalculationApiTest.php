<?php

namespace Tests\Feature;

use App\Models\CraftingMethod;
use App\Models\Item;
use App\Models\Recipe;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CalculationApiTest extends TestCase
{
    use RefreshDatabase;

    /** Helper to set up a simple recipe chain for testing */
    private function setupRecipeChain()
    {
        // Raw Materials
        $rawIron = Item::factory()->asRaw()->create(['name' => 'Iron Ore']);
        $rawCopper = Item::factory()->asRaw()->create(['name' => 'Copper Ore']);

        // Intermediate Item
        $plateIron = Item::factory()->asCraftable()->create(['name' => 'Iron Plate']);

        // Final Item
        $wireCopper = Item::factory()->asCraftable()->create(['name' => 'Copper Wire']);

        // Crafting Method
        $assembler = CraftingMethod::factory()->create(['name' => 'Assembler']);
        $cutter = CraftingMethod::factory()->create(['name' => 'Cutter']);

        // Recipe 1: 2 Iron Ore -> 1 Iron Plate (Assembler, 100% chance)
        $recipePlate = Recipe::factory()->create([
            'crafting_method_id' => $assembler->id,
            'name' => 'Make Iron Plate'
        ]);
        $recipePlate->inputs()->createMany([
            ['input_item_id' => $rawIron->id, 'input_quantity' => 2],
        ]);
        $recipePlate->outputs()->create([
            'item_id' => $plateIron->id,
            'quantity' => 1,
            'chance' => 10000,
            'is_primary_output' => true,
        ]);

        // Recipe 2: 1 Iron Plate -> 2 Copper Wire (Cutter, 100% chance)
        $recipeWire = Recipe::factory()->create([
            'crafting_method_id' => $cutter->id,
            'name' => 'Make Copper Wire'
        ]);
        $recipeWire->inputs()->create([
            'input_item_id' => $plateIron->id,
            'input_quantity' => 1,
        ]);
        $recipeWire->outputs()->createMany([
            ['item_id' => $wireCopper->id, 'quantity' => 2, 'chance' => 10000, 'is_primary_output' => true],
        ]);

        // Return IDs for easy access in tests
        return compact('rawIron', 'rawCopper', 'plateIron', 'wireCopper', 'recipePlate', 'recipeWire');
    }

    // === POST /api/v1/calculations ===

    public function test_can_calculate_simple_recipe_chain(): void
    {
        $data = $this->setupRecipeChain();

        $payload = [
            'item_id' => $data['wireCopper']->id, // Target: Copper Wire
            'quantity' => 4, // Request 4 wires
        ];

        $response = $this->postJson('/api/v1/calculations', $payload);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'calculation_request' => ['item_id', 'item_name', 'quantity'],
                'detailed_breakdown', // We won't assert the whole complex tree here
                'aggregated_raw_materials' => ['*' => ['item_id', 'name', 'total_quantity']]
            ])
            ->assertJsonPath('calculation_request.item_id', $data['wireCopper']->id)
            ->assertJsonPath('calculation_request.quantity', 4);

        // Assert Raw Materials: Need 4 wires -> Need 2 plates -> Need 4 Iron Ore
        $response->assertJsonCount(1, 'aggregated_raw_materials'); // Only Iron Ore is raw in this chain
        $response->assertJsonPath('aggregated_raw_materials.0.item_id', $data['rawIron']->id);
        // Use assertJsonPath with float check or assertJsonFragment for quantities
        $response->assertJsonFragment(['total_quantity' => 4.0]); // Check if 4.0 Iron Ore is needed
    }

    public function test_calculation_fails_with_missing_item_id(): void
    {
        $response = $this->postJson('/api/v1/calculations', ['quantity' => 1]);
        $response->assertStatus(422)->assertJsonValidationErrors(['item_id']);
    }

    public function test_calculation_fails_with_missing_quantity(): void
    {
        $item = Item::factory()->create();
        $response = $this->postJson('/api/v1/calculations', ['item_id' => $item->id]);
        $response->assertStatus(422)->assertJsonValidationErrors(['quantity']);
    }

    public function test_calculation_fails_with_zero_quantity(): void
    {
        $item = Item::factory()->create();
        $response = $this->postJson('/api/v1/calculations', ['item_id' => $item->id, 'quantity' => 0]);
        $response->assertStatus(422)->assertJsonValidationErrors(['quantity']); // gt:0 rule
    }

    public function test_calculation_fails_with_non_existent_item_id(): void
    {
        $response = $this->postJson('/api/v1/calculations', ['item_id' => 9999, 'quantity' => 1]);
        $response->assertStatus(422)->assertJsonValidationErrors(['item_id']); // exists rule
    }
}
