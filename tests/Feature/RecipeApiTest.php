<?php

namespace Tests\Feature;

use App\Models\CraftingMethod;
use App\Models\Item;
use App\Models\Recipe;
use App\Models\User;
// Remove use App\Models\User; - Not needed without auth
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecipeApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    public function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    // Helper to set up common data for recipe tests
    private function setupRecipeTestData(): array
    {
        $itemInput1 = Item::factory()->create(['name' => 'Test Input 1']);
        $itemInput2 = Item::factory()->create(['name' => 'Test Input 2']);
        $itemOutput1 = Item::factory()->create(['name' => 'Test Output 1']);
        $itemOutput2 = Item::factory()->create(['name' => 'Test Output 2 (Byproduct)']);
        $craftingMethod = CraftingMethod::factory()->create();

        // Return without user
        return compact('itemInput1', 'itemInput2', 'itemOutput1', 'itemOutput2', 'craftingMethod');
    }

    // === GET /api/v1/recipes (Index) ===
    public function test_can_get_list_of_recipes(): void
    {
        $data = $this->setupRecipeTestData();
        $recipe = Recipe::factory()->create(['crafting_method_id' => $data['craftingMethod']->id]);
        $recipe->outputs()->create(['item_id' => $data['itemOutput1']->id, 'quantity' => 1, 'chance' => 10000, 'is_primary_output' => true]);

        $response = $this->getJson('/api/v1/recipes');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => ['*' => [
                    'id',
                    'type',
                    'attributes',
                    'relationships' => ['crafting_method', 'primary_output_item'],
                    'links'
                ]],
                'links',
                'meta'
            ])
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.relationships.primary_output_item.attributes.name', $data['itemOutput1']->name);
    }

    // === GET /api/v1/recipes/{recipe} (Show) ===
    public function test_can_get_single_recipe_with_relations(): void
    {
        $data = $this->setupRecipeTestData();
        $recipe = Recipe::factory()->create(['crafting_method_id' => $data['craftingMethod']->id]);
        $recipe->inputs()->create(['input_item_id' => $data['itemInput1']->id, 'input_quantity' => 2]);
        $recipe->outputs()->create(['item_id' => $data['itemOutput1']->id, 'quantity' => 1, 'chance' => 10000, 'is_primary_output' => true]);

        $response = $this->getJson("/api/v1/recipes/{$recipe->id}?include=inputs.inputItem,outputs.item,craftingMethod");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'attributes',
                    'relationships' => [
                        'crafting_method',
                        'inputs' => ['*' => ['item', 'quantity']],
                        'outputs' => ['*' => ['item', 'quantity', 'chance', 'is_primary']]
                    ],
                    'links'
                ]
            ])
            ->assertJsonPath('data.id', $recipe->id)
            ->assertJsonPath('data.relationships.inputs.0.item.attributes.name', $data['itemInput1']->name)
            ->assertJsonPath('data.relationships.outputs.0.item.attributes.name', $data['itemOutput1']->name);
    }

    public function test_returns_404_for_non_existent_recipe(): void
    {
        $response = $this->getJson('/api/v1/recipes/9999');
        $response->assertStatus(404);
    }

    // === POST /api/v1/recipes (Store) ===
    public function test_guest_cannot_create_recipes(): void
    {
        $data = $this->setupRecipeTestData();
        $payload = [
            'name' => 'Test Recipe Creation',
            'crafting_method_id' => $data['craftingMethod']->id,
            'eu_per_tick' => 32,
            'duration_ticks' => 200,
            'is_default' => false,
            'inputs' => [
                ['input_item_id' => $data['itemInput1']->id, 'input_quantity' => 5],
                ['input_item_id' => $data['itemInput2']->id, 'input_quantity' => 1],
            ],
            'outputs' => [
                ['item_id' => $data['itemOutput1']->id, 'quantity' => 1, 'chance' => 10000, 'is_primary_output' => true],
                ['item_id' => $data['itemOutput2']->id, 'quantity' => 1, 'chance' => 5000, 'is_primary_output' => false],
            ]
        ];

        $response = $this->postJson('/api/v1/recipes', $payload);

        $response->assertStatus(401);

        $this->assertDatabaseMissing('recipes', ['name' => 'Test Recipe Creation']);
    }

    public function test_authenticated_user_can_create_recipes_with_relations(): void
    {
        $data = $this->setupRecipeTestData();
        $payload = [
            'name' => 'Test Recipe Creation',
            'crafting_method_id' => $data['craftingMethod']->id,
            'eu_per_tick' => 32,
            'duration_ticks' => 200,
            'is_default' => false,
            'inputs' => [
                ['input_item_id' => $data['itemInput1']->id, 'input_quantity' => 5],
                ['input_item_id' => $data['itemInput2']->id, 'input_quantity' => 1],
            ],
            'outputs' => [
                ['item_id' => $data['itemOutput1']->id, 'quantity' => 1, 'chance' => 10000, 'is_primary_output' => true],
                ['item_id' => $data['itemOutput2']->id, 'quantity' => 1, 'chance' => 5000, 'is_primary_output' => false],
            ]
        ];

        $response = $this->actingAs($this->user)->postJson('/api/v1/recipes', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('data.attributes.name', 'Test Recipe Creation')
            ->assertJsonCount(2, 'data.relationships.inputs')
            ->assertJsonCount(2, 'data.relationships.outputs');

        $createdRecipeId = $response->json('data.id');
        $this->assertDatabaseHas('recipes', ['id' => $createdRecipeId, 'eu_per_tick' => 32]);
        $this->assertDatabaseHas('recipe_inputs', ['recipe_id' => $createdRecipeId, 'input_item_id' => $data['itemInput1']->id, 'input_quantity' => 5]);
        $this->assertDatabaseHas('recipe_inputs', ['recipe_id' => $createdRecipeId, 'input_item_id' => $data['itemInput2']->id, 'input_quantity' => 1]);
        $this->assertDatabaseHas('recipe_outputs', ['recipe_id' => $createdRecipeId, 'item_id' => $data['itemOutput1']->id, 'chance' => 10000, 'is_primary_output' => true]);
        $this->assertDatabaseHas('recipe_outputs', ['recipe_id' => $createdRecipeId, 'item_id' => $data['itemOutput2']->id, 'chance' => 5000, 'is_primary_output' => false]);
    }

    public function test_recipe_creation_fails_without_crafting_method(): void
    {
        $data = $this->setupRecipeTestData();
        $payload = ['inputs' => [], 'outputs' => []];
        $response = $this->actingAs($this->user)->postJson('/api/v1/recipes', $payload);
        $response->assertStatus(422)->assertJsonValidationErrors(['crafting_method_id']);
    }

    public function test_recipe_creation_fails_without_inputs(): void
    {
        $data = $this->setupRecipeTestData();
        $payload = ['crafting_method_id' => $data['craftingMethod']->id, 'outputs' => []];
        $response = $this->actingAs($this->user)->postJson('/api/v1/recipes', $payload);
        $response->assertStatus(422)->assertJsonValidationErrors(['inputs']);
    }

    public function test_recipe_creation_fails_without_outputs(): void
    {
        $data = $this->setupRecipeTestData();
        $payload = ['crafting_method_id' => $data['craftingMethod']->id, 'inputs' => [['input_item_id' => $data['itemInput1']->id, 'input_quantity' => 1]], /* missing outputs */];
        $response = $this->actingAs($this->user)->postJson('/api/v1/recipes', $payload);
        $response->assertStatus(422)->assertJsonValidationErrors(['outputs']);
    }

    public function test_recipe_creation_fails_with_duplicate_input_item(): void
    {
        $data = $this->setupRecipeTestData();
        $payload = [
            'crafting_method_id' => $data['craftingMethod']->id,
            'inputs' => [
                ['input_item_id' => $data['itemInput1']->id, 'input_quantity' => 1],
                ['input_item_id' => $data['itemInput1']->id, 'input_quantity' => 2], // Duplicate item
            ],
            'outputs' => [['item_id' => $data['itemOutput1']->id, 'quantity' => 1, 'chance' => 10000, 'is_primary_output' => true]],
        ];
        $response = $this->actingAs($this->user)->postJson('/api/v1/recipes', $payload);
        $response->assertStatus(422)->assertJsonValidationErrors(['inputs']);
    }

    public function test_recipe_creation_fails_with_duplicate_output_item(): void
    {
        $data = $this->setupRecipeTestData();
        $payload = [
            'crafting_method_id' => $data['craftingMethod']->id,
            'inputs' => [['input_item_id' => $data['itemInput1']->id, 'input_quantity' => 1]],
            'outputs' => [
                ['item_id' => $data['itemOutput1']->id, 'quantity' => 1, 'chance' => 10000, 'is_primary_output' => true],
                ['item_id' => $data['itemOutput1']->id, 'quantity' => 1, 'chance' => 5000, 'is_primary_output' => false], // Duplicate item
            ],
        ];
        $response = $this->actingAs($this->user)->postJson('/api/v1/recipes', $payload);
        $response->assertStatus(422)->assertJsonValidationErrors(['outputs']);
    }

    public function test_recipe_creation_fails_with_no_primary_output(): void
    {
        $data = $this->setupRecipeTestData();
        $payload = [
            'crafting_method_id' => $data['craftingMethod']->id,
            'inputs' => [['input_item_id' => $data['itemInput1']->id, 'input_quantity' => 1]],
            'outputs' => [
                ['item_id' => $data['itemOutput1']->id, 'quantity' => 1, 'chance' => 10000, 'is_primary_output' => false], // Not primary
            ],
        ];
        $response = $this->actingAs($this->user)->postJson('/api/v1/recipes', $payload);
        $response->assertStatus(422)->assertJsonValidationErrors(['outputs']);
    }

    public function test_recipe_creation_fails_with_multiple_primary_outputs(): void
    {
        $data = $this->setupRecipeTestData();
        $payload = [
            'crafting_method_id' => $data['craftingMethod']->id,
            'inputs' => [['input_item_id' => $data['itemInput1']->id, 'input_quantity' => 1]],
            'outputs' => [
                ['item_id' => $data['itemOutput1']->id, 'quantity' => 1, 'chance' => 10000, 'is_primary_output' => true],
                ['item_id' => $data['itemOutput2']->id, 'quantity' => 1, 'chance' => 5000, 'is_primary_output' => true],
            ],
        ];
        $response = $this->actingAs($this->user)->postJson('/api/v1/recipes', $payload);
        $response->assertStatus(422)->assertJsonValidationErrors(['outputs']);
    }


    // === PUT/PATCH /api/v1/recipes/{recipe} (Update) ===
    public function test_guest_cannot_update_recipes(): void
    {
        $data = $this->setupRecipeTestData();
        $recipe = Recipe::factory()->create([
            'name' => 'Old Recipe Name',
            'crafting_method_id' => $data['craftingMethod']->id,
            'duration_ticks' => 100,
        ]);
        $inputToDelete = $recipe->inputs()->create(['input_item_id' => $data['itemInput1']->id, 'input_quantity' => 1]);
        $recipe->inputs()->create(['input_item_id' => $data['itemInput2']->id, 'input_quantity' => 1]);
        $outputToDelete = $recipe->outputs()->create(['item_id' => $data['itemOutput1']->id, 'quantity' => 1, 'chance' => 10000, 'is_primary_output' => true]);
        $recipe->outputs()->create(['item_id' => $data['itemOutput2']->id, 'quantity' => 1, 'chance' => 5000, 'is_primary_output' => false]);
        $response = $this->putJson('/api/v1/recipes/' . $recipe->id, [
            'name' => 'New Recipe Name',
            'crafting_method_id' => $data['craftingMethod']->id,
            'duration_ticks' => 200,
            'inputs' => [
                ['input_item_id' => $data['itemInput1']->id, 'input_quantity' => 1],
                ['input_item_id' => $data['itemInput2']->id, 'input_quantity' => 1],
            ],
            'outputs' => [
                ['item_id' => $data['itemOutput1']->id, 'quantity' => 1, 'chance' => 10000, 'is_primary_output' => true],
                ['item_id' => $data['itemOutput2']->id, 'quantity' => 1, 'chance' => 5000, 'is_primary_output' => false],
            ],
        ]);

        $response->assertStatus(401);

        $recipe->refresh();

        $this->assertEquals('Old Recipe Name', $recipe->name);
    }

    public function test_authenticated_user_can_update_recipes_and_sync_relations(): void
    {
        $data = $this->setupRecipeTestData();
        $recipe = Recipe::factory()->create([
            'name' => 'Old Recipe Name',
            'crafting_method_id' => $data['craftingMethod']->id,
            'duration_ticks' => 100,
        ]);
        $inputToDelete = $recipe->inputs()->create(['input_item_id' => $data['itemInput1']->id, 'input_quantity' => 1]);
        $recipe->inputs()->create(['input_item_id' => $data['itemInput2']->id, 'input_quantity' => 1]);
        $outputToDelete = $recipe->outputs()->create(['item_id' => $data['itemOutput1']->id, 'quantity' => 1, 'chance' => 10000, 'is_primary_output' => true]);
        $recipe->outputs()->create(['item_id' => $data['itemOutput2']->id, 'quantity' => 1, 'chance' => 5000, 'is_primary_output' => false]);
        $newItemInput = Item::factory()->create(['name' => 'New Input']);
        $newItemOutput = Item::factory()->create(['name' => 'New Output']);

        $payload = [
            'name' => 'New Recipe Name',
            'crafting_method_id' => $data['craftingMethod']->id,
            'duration_ticks' => 150,
            'is_default' => true,
            'inputs' => [
                ['input_item_id' => $data['itemInput2']->id, 'input_quantity' => 5],
                ['input_item_id' => $newItemInput->id, 'input_quantity' => 10],
            ],
            'outputs' => [
                ['item_id' => $data['itemOutput2']->id, 'quantity' => 2, 'chance' => 6000, 'is_primary_output' => true],
                ['item_id' => $newItemOutput->id, 'quantity' => 3, 'chance' => 10000, 'is_primary_output' => false],
            ]
        ];

        $response = $this->actingAs($this->user)->putJson("/api/v1/recipes/{$recipe->id}", $payload);

        $response->assertStatus(200)
            ->assertJsonPath('data.attributes.name', 'New Recipe Name')
            ->assertJsonPath('data.attributes.duration_ticks', 150)
            ->assertJsonPath('data.attributes.is_default', true)
            ->assertJsonCount(2, 'data.relationships.inputs')
            ->assertJsonCount(2, 'data.relationships.outputs');

        $this->assertDatabaseHas('recipes', ['id' => $recipe->id, 'name' => 'New Recipe Name']);
        $this->assertDatabaseMissing('recipe_inputs', ['id' => $inputToDelete->id]);
        $this->assertDatabaseHas('recipe_inputs', [
            'recipe_id' => $recipe->id,
            'input_item_id' => $data['itemInput2']->id,
            'input_quantity' => 5
        ]);
        $this->assertDatabaseHas('recipe_inputs', [
            'recipe_id' => $recipe->id,
            'input_item_id' => $newItemInput->id,
            'input_quantity' => 10
        ]);
        $this->assertDatabaseMissing('recipe_outputs', ['id' => $outputToDelete->id]);
        $this->assertDatabaseHas('recipe_outputs', [
            'recipe_id' => $recipe->id,
            'item_id' => $data['itemOutput2']->id,
            'quantity' => 2,
            'chance' => 6000,
            'is_primary_output' => true
        ]);
        $this->assertDatabaseHas('recipe_outputs', [
            'recipe_id' => $recipe->id,
            'item_id' => $newItemOutput->id,
            'quantity' => 3
        ]);
    }

    // === DELETE /api/v1/recipes/{recipe} (Destroy) ===
    public function test_guest_cannot_delete_recipes(): void
    {
        $recipe = Recipe::factory()->create();
        $response = $this->deleteJson("/api/v1/recipes/{$recipe->id}");
        $response->assertStatus(401);
        $this->assertDatabaseHas('recipes', ['id' => $recipe->id]);
    }

    public function test_authenticated_user_can_delete_recipes_and_cascades(): void
    {
        $data = $this->setupRecipeTestData();
        $recipe = Recipe::factory()->create(['crafting_method_id' => $data['craftingMethod']->id]);
        $input = $recipe->inputs()->create(['input_item_id' => $data['itemInput1']->id, 'input_quantity' => 1]);
        $output = $recipe->outputs()->create(['item_id' => $data['itemOutput1']->id, 'quantity' => 1, 'chance' => 10000, 'is_primary_output' => true]);

        $response = $this->actingAs($this->user)->deleteJson("/api/v1/recipes/{$recipe->id}");

        $response->assertStatus(204);
        $this->assertDatabaseMissing('recipes', ['id' => $recipe->id]);
        $this->assertDatabaseMissing('recipe_inputs', ['id' => $input->id]);
        $this->assertDatabaseMissing('recipe_outputs', ['id' => $output->id]);
    }

    public function test_recipe_delete_fails_for_non_existent_recipe(): void
    {
        $response = $this->actingAs($this->user)->deleteJson("/api/v1/recipes/9999");
        $response->assertStatus(404);
    }
}
