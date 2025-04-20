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

    // --- Setup Helpers ---

    /** Helper to set up a simple recipe chain for testing */
    private function setupRecipeChain(): array
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
        $recipePlate = Recipe::factory()->create(['crafting_method_id' => $assembler->id, 'name' => 'Make Iron Plate']);
        $recipePlate->inputs()->createMany([['input_item_id' => $rawIron->id, 'input_quantity' => 2]]);
        $recipePlate->outputs()->create(['item_id' => $plateIron->id, 'quantity' => 1, 'chance' => 10000, 'is_primary_output' => true]);

        // Recipe 2: 1 Iron Plate -> 2 Copper Wire (Cutter, 100% chance)
        $recipeWire = Recipe::factory()->create(['crafting_method_id' => $cutter->id, 'name' => 'Make Copper Wire']);
        $recipeWire->inputs()->create(['input_item_id' => $plateIron->id, 'input_quantity' => 1]);
        $recipeWire->outputs()->createMany([['item_id' => $wireCopper->id, 'quantity' => 2, 'chance' => 10000, 'is_primary_output' => true]]);

        return compact('rawIron', 'rawCopper', 'plateIron', 'wireCopper', 'recipePlate', 'recipeWire', 'assembler', 'cutter');
    }

    // --- Basic Calculation & Validation Tests ---
    public function test_can_calculate_simple_recipe_chain(): void
    {
        $data = $this->setupRecipeChain();
        $payload = ['item_id' => $data['wireCopper']->id, 'quantity' => 4];
        $response = $this->postJson('/api/v1/calculations', $payload);

        $response->assertStatus(200)
            ->assertJsonStructure(['calculation_request', 'detailed_breakdown', 'aggregated_raw_materials'])
            ->assertJsonPath('calculation_request.item_id', $data['wireCopper']->id)
            ->assertJsonPath('calculation_request.quantity', 4)
            ->assertJsonCount(1, 'aggregated_raw_materials')
            ->assertJsonPath('aggregated_raw_materials.0.item_id', $data['rawIron']->id)
            ->assertJsonFragment(['total_quantity' => 4.0]); // 4 wire -> 2 plate -> 4 iron ore
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
        $response->assertStatus(422)->assertJsonValidationErrors(['quantity']);
    }

    public function test_calculation_fails_with_non_existent_item_id(): void
    {
        $response = $this->postJson('/api/v1/calculations', ['item_id' => 9999, 'quantity' => 1]);
        // This can be 422 (validation 'exists' rule) or 404 if validation passes but service throws ItemNotFoundException
        // Asserting 4xx is generally okay here, but 422 is expected from the FormRequest
        $response->assertStatus(422)->assertJsonValidationErrors(['item_id']);
    }

    // --- Calculation Service Logic Tests ---
    public function test_calculates_raw_material_directly(): void
    {
        $rawItem = Item::factory()->asRaw()->create(['name' => 'Raw Sand']);
        $payload = ['item_id' => $rawItem->id, 'quantity' => 123.5];
        $response = $this->postJson('/api/v1/calculations', $payload);

        $response->assertStatus(200)
            ->assertJsonCount(1, 'aggregated_raw_materials')
            ->assertJsonPath('aggregated_raw_materials.0.item_id', $rawItem->id)
            ->assertJsonFragment(['total_quantity' => 123.5]); // Use fragment for float check

        // Also check breakdown for raw item
        $response->assertJsonPath('detailed_breakdown.item_id', $rawItem->id);
        $response->assertJsonPath('detailed_breakdown.is_raw', true);
        $response->assertJsonPath('detailed_breakdown.required_quantity', 123.5);
        $response->assertJsonMissingPath('detailed_breakdown.sub_steps'); // Raw has no sub-steps
    }

    public function test_treats_craftable_item_with_no_recipe_as_raw(): void
    {
        $craftableNoRecipe = Item::factory()->asCraftable()->create(['name' => 'Missing Recipe Item']);
        $payload = ['item_id' => $craftableNoRecipe->id, 'quantity' => 50];
        $response = $this->postJson('/api/v1/calculations', $payload);

        $response->assertStatus(200)
            ->assertJsonCount(1, 'aggregated_raw_materials')
            ->assertJsonPath('aggregated_raw_materials.0.item_id', $craftableNoRecipe->id)
            ->assertJsonFragment(['total_quantity' => 50.0]);

        // Check breakdown structure indicates it was treated as raw
        $response->assertJsonPath('detailed_breakdown.item_id', $craftableNoRecipe->id);
        $response->assertJsonPath('detailed_breakdown.is_raw', true); // Service flips this
        $response->assertJsonPath('detailed_breakdown.required_quantity', 50);
        $response->assertJsonPath('detailed_breakdown.warning', 'No recipe found to produce this item.'); // Check warning
        $response->assertJsonMissingPath('detailed_breakdown.sub_steps');
    }

    public function test_calculates_recipe_with_chance_correctly(): void
    {
        $raw = Item::factory()->asRaw()->create(['name' => 'Chance Input Ore']);
        $target = Item::factory()->asCraftable()->create(['name' => 'Chance Output Ingot']);
        $method = CraftingMethod::factory()->create();

        // Recipe: 1 Raw -> 1 Target (50% chance)
        $recipe = Recipe::factory()->create(['crafting_method_id' => $method->id]);
        $recipe->inputs()->create(['input_item_id' => $raw->id, 'input_quantity' => 1]);
        $recipe->outputs()->create(['item_id' => $target->id, 'quantity' => 1, 'chance' => 5000, 'is_primary_output' => true]); // 5000 = 50%

        $payload = ['item_id' => $target->id, 'quantity' => 1]; // Request 1 ingot
        $response = $this->postJson('/api/v1/calculations', $payload);

        // Expected: Need 1 avg. yield. Each cycle yields 0.5. Cycles needed = ceil(1/0.5) = 2.
        // Raw needed = 1 (per cycle) * 2 (cycles) = 2.
        $response->assertStatus(200)
            ->assertJsonPath('detailed_breakdown.cycles_needed', 2)
            ->assertJsonCount(1, 'aggregated_raw_materials')
            ->assertJsonPath('aggregated_raw_materials.0.item_id', $raw->id)
            ->assertJsonFragment(['total_quantity' => 2.0]);
    }

    public function test_calculates_recipe_with_byproduct(): void
    {
        $rawA = Item::factory()->asRaw()->create(['name' => 'Byproduct Input A']);
        $rawB = Item::factory()->asRaw()->create(['name' => 'Byproduct Input B']);
        $target = Item::factory()->asCraftable()->create(['name' => 'Target With Byproduct']);
        $byproduct = Item::factory()->asCraftable()->create(['name' => 'Useful Byproduct']); // Could be raw too
        $method = CraftingMethod::factory()->create();

        // Recipe: 1 A + 1 B -> 1 Target (100%) + 1 Byproduct (100%)
        $recipe = Recipe::factory()->create(['crafting_method_id' => $method->id]);
        $recipe->inputs()->createMany([
            ['input_item_id' => $rawA->id, 'input_quantity' => 1],
            ['input_item_id' => $rawB->id, 'input_quantity' => 1]
        ]);
        $recipe->outputs()->createMany([
            ['item_id' => $target->id, 'quantity' => 1, 'chance' => 10000, 'is_primary_output' => true],
            ['item_id' => $byproduct->id, 'quantity' => 1, 'chance' => 10000, 'is_primary_output' => false], // Byproduct
        ]);

        $payload = ['item_id' => $target->id, 'quantity' => 3]; // Request 3 target items
        $response = $this->postJson('/api/v1/calculations', $payload);

        // Expected: Need 3 cycles. Need 3 Raw A, 3 Raw B. Byproduct ignored in raw list.
        $response->assertStatus(200)
            ->assertJsonPath('detailed_breakdown.cycles_needed', 3)
            ->assertJsonCount(2, 'aggregated_raw_materials') // Expecting 2 raw types
            ->assertJsonFragment(['item_id' => $rawA->id, 'total_quantity' => 3.0]) // Check Raw A quantity
            ->assertJsonFragment(['item_id' => $rawB->id, 'total_quantity' => 3.0]); // Check Raw B quantity

        // Check breakdown includes byproduct data fragment within the expected_outputs array
        $response->assertJsonFragment([
            'item_id' => $byproduct->id,
            'item_name' => $byproduct->name,
            'is_primary' => false,
            'total_expected_yield' => 3.0
        ], $response->json('detailed_breakdown.expected_outputs'));

        $response->assertJsonCount(2, 'detailed_breakdown.expected_outputs');
    }

    public function test_chooses_default_recipe_when_multiple_exist(): void
    {
        $rawA = Item::factory()->asRaw()->create(['name' => 'Input A']);
        $rawB = Item::factory()->asRaw()->create(['name' => 'Input B']);
        $target = Item::factory()->asCraftable()->create(['name' => 'Multi-Recipe Target']);
        $method = CraftingMethod::factory()->create();

        // Recipe 1 (Non-default): 1 Raw A -> 1 Target
        $recipeA = Recipe::factory()->create(['crafting_method_id' => $method->id, 'is_default' => false, 'name' => 'Recipe A']);
        $recipeA->inputs()->create(['input_item_id' => $rawA->id, 'input_quantity' => 1]);
        $recipeA->outputs()->create(['item_id' => $target->id, 'quantity' => 1, 'chance' => 10000, 'is_primary_output' => true]);

        // Recipe 2 (Default): 2 Raw B -> 1 Target
        $recipeB = Recipe::factory()->create(['crafting_method_id' => $method->id, 'is_default' => true, 'name' => 'Recipe B (Default)']);
        $recipeB->inputs()->create(['input_item_id' => $rawB->id, 'input_quantity' => 2]);
        $recipeB->outputs()->create(['item_id' => $target->id, 'quantity' => 1, 'chance' => 10000, 'is_primary_output' => true]);

        $payload = ['item_id' => $target->id, 'quantity' => 1];
        $response = $this->postJson('/api/v1/calculations', $payload);

        // Expected: Recipe B (default) should be used. Need 1 cycle. Need 2 Raw B.
        $response->assertStatus(200)
            ->assertJsonPath('detailed_breakdown.recipe_used.id', $recipeB->id) // Check correct recipe used
            ->assertJsonCount(1, 'aggregated_raw_materials')
            ->assertJsonPath('aggregated_raw_materials.0.item_id', $rawB->id)
            ->assertJsonFragment(['total_quantity' => 2.0]);
    }

    public function test_treats_input_item_with_no_recipe_as_raw_in_chain(): void
    {
        $raw = Item::factory()->asRaw()->create(['name' => 'Base Raw Material']);
        $intermediateNoRecipe = Item::factory()->asCraftable()->create(['name' => 'Intermediate Lacking Recipe']); // No recipe outputs this
        $target = Item::factory()->asCraftable()->create(['name' => 'Final Target']);
        $method = CraftingMethod::factory()->create();

        // Recipe 1: Raw -> IntermediateNoRecipe
        $recipe1 = Recipe::factory()->create(['crafting_method_id' => $method->id]);
        $recipe1->inputs()->create(['input_item_id' => $raw->id, 'input_quantity' => 1]);
        $recipe1->outputs()->create(['item_id' => $intermediateNoRecipe->id, 'quantity' => 1, 'chance' => 10000, 'is_primary_output' => true]);

        // Recipe 2: IntermediateNoRecipe -> Target
        $recipe2 = Recipe::factory()->create(['crafting_method_id' => $method->id]);
        $recipe2->inputs()->create(['input_item_id' => $intermediateNoRecipe->id, 'input_quantity' => 1]);
        $recipe2->outputs()->create(['item_id' => $target->id, 'quantity' => 1, 'chance' => 10000, 'is_primary_output' => true]);

        $payload = ['item_id' => $target->id, 'quantity' => 1];
        $response = $this->postJson('/api/v1/calculations', $payload);

        // Expected: Need 1 Target -> Need 1 Intermediate. Intermediate has no recipe, so it's treated as raw.
        // Final raw mats: 1 IntermediateNoRecipe. (The initial 'raw' isn't needed as its product is treated as raw)
        // CORRECTION: Need 1 Target -> Need 1 Intermediate -> Service treats Intermediate as raw -> Need 1 Intermediate
        // BUT, to *get* the intermediate, we still needed the *original* raw material from Recipe 1.
        // The aggregation should find the true raw material at the end of the branches.
        // Let's trace: Target(1) -> Needs Intermediate(1). Intermediate(1) has Recipe1. -> Needs Raw(1).
        // The service *should* correctly trace back to the original raw material.

        $response->assertStatus(200)
            ->assertJsonCount(1, 'aggregated_raw_materials') // Should trace back to the original raw
            ->assertJsonPath('aggregated_raw_materials.0.item_id', $raw->id)
            ->assertJsonFragment(['total_quantity' => 1.0]);

        // Let's check the breakdown structure for the warning about the intermediate step
        $breakdown = $response->json('detailed_breakdown');
        $this->assertEquals($target->id, $breakdown['item_id']);
        $this->assertCount(1, $breakdown['sub_steps']);
        $intermediateNode = $breakdown['sub_steps'][0];
        $this->assertEquals($intermediateNoRecipe->id, $intermediateNode['item_id']);
        // The intermediate node ITSELF should not be marked as raw here if a recipe was FOUND for it (Recipe 1)
        $this->assertFalse($intermediateNode['is_raw']);
        $this->assertArrayHasKey('recipe_used', $intermediateNode); // It used Recipe 1
        $this->assertEquals($recipe1->id, $intermediateNode['recipe_used']['id']);
        // Check the sub-step of the intermediate node, which should be the raw material
        $this->assertCount(1, $intermediateNode['sub_steps']);
        $rawNode = $intermediateNode['sub_steps'][0];
        $this->assertEquals($raw->id, $rawNode['item_id']);
        $this->assertTrue($rawNode['is_raw']);

        // --> The previous interpretation was slightly off. The service *should* calculate the actual raw,
        //     but maybe the 'no recipe found' logic needs refinement if it incorrectly marks intermediates as raw too early.
        //     Let's keep the assertion based on the expected *correct* behavior (finding the base raw).
    }

    public function test_calculation_fails_for_recipe_with_zero_yield(): void
    {
        $raw = Item::factory()->asRaw()->create();
        $target = Item::factory()->asCraftable()->create();
        $method = CraftingMethod::factory()->create();

        // Recipe: 1 Raw -> 1 Target (0% chance)
        $recipe = Recipe::factory()->create(['crafting_method_id' => $method->id]);
        $recipe->inputs()->create(['input_item_id' => $raw->id, 'input_quantity' => 1]);
        $recipe->outputs()->create(['item_id' => $target->id, 'quantity' => 1, 'chance' => 0, 'is_primary_output' => true]);

        $payload = ['item_id' => $target->id, 'quantity' => 1];
        $response = $this->postJson('/api/v1/calculations', $payload);

        // Expecting a 400 Bad Request or similar due to CalculationException
        $response->assertStatus(400);
        $response->assertJsonFragment(['message' => "Recipe {$recipe->id} has zero average yield for item ID {$target->id}. Cannot calculate cycles."]); // Check specific error
    }
}
