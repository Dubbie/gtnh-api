<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ItemApiTest extends TestCase
{
    use RefreshDatabase;

    // === GET /api/v1/items (Index) ===
    public function test_can_get_list_of_items(): void
    {
        Item::factory()->count(5)->create();

        $response = $this->getJson('/api/v1/items');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => ['*' => ['id', 'type', 'attributes' => ['name', 'slug'], 'links']],
                'links',
                'meta'
            ])
            ->assertJsonCount(5, 'data');
    }

    public function test_items_list_respects_per_page_parameter(): void
    {
        Item::factory()->count(10)->create();

        $response = $this->getJson('/api/v1/items?per_page=3');

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('meta.per_page', 3);
    }

    // === GET /api/v1/items/{item} (Show) ===
    public function test_can_get_single_item(): void
    {
        $item = Item::factory()->create();

        $response = $this->getJson("/api/v1/items/{$item->id}");

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => ['id', 'type', 'attributes', 'links']])
            ->assertJsonPath('data.id', $item->id)
            ->assertJsonPath('data.attributes.name', $item->name);
    }

    public function test_returns_404_for_non_existent_item(): void
    {
        $response = $this->getJson('/api/v1/items/99999');
        $response->assertStatus(404);
    }

    // === POST /api/v1/items (Store) ===
    public function test_can_create_items(): void
    {
        $itemData = [
            'name' => 'Test Copper Wire',
            'is_raw_material' => false,
            'description' => 'A test wire.',
        ];

        $response = $this->postJson('/api/v1/items', $itemData);

        $response->assertStatus(201)
            ->assertJsonStructure(['data' => ['id', 'attributes' => ['name', 'slug']]])
            ->assertJsonPath('data.attributes.name', 'Test Copper Wire');

        $this->assertDatabaseHas('items', [
            'name' => 'Test Copper Wire',
            'slug' => 'test-copper-wire',
            'is_raw_material' => false,
        ]);
    }

    public function test_item_creation_fails_with_missing_name(): void
    {
        $user = User::factory()->create();
        $itemData = ['is_raw_material' => false]; // Missing 'name'

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/items', $itemData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_item_creation_fails_with_duplicate_name(): void
    {
        $user = User::factory()->create();
        $existingItem = Item::factory()->create(['name' => 'Duplicate Item']);
        $itemData = ['name' => 'Duplicate Item']; // Same name

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/items', $itemData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    // === PUT/PATCH /api/v1/items/{item} (Update) ===
    public function test_can_update_items(): void
    {
        $item = Item::factory()->create(['name' => 'Original Name']);
        $updateData = ['name' => 'Updated Name', 'description' => 'New Desc'];

        $response = $this->putJson("/api/v1/items/{$item->id}", $updateData);

        $response->assertStatus(200)
            ->assertJsonPath('data.attributes.name', 'Updated Name')
            ->assertJsonPath('data.attributes.description', 'New Desc');

        $this->assertDatabaseHas('items', [
            'id' => $item->id,
            'name' => 'Updated Name',
            'description' => 'New Desc',
        ]);
    }

    public function test_item_update_fails_for_non_existent_item(): void
    {
        $user = User::factory()->create();
        $updateData = ['name' => 'Updated Name'];

        $response = $this->actingAs($user, 'sanctum')
            ->putJson("/api/v1/items/99999", $updateData);

        $response->assertStatus(404);
    }

    // === DELETE /api/v1/items/{item} (Destroy) ===
    public function test_can_delete_items(): void
    {
        $item = Item::factory()->create();

        $response = $this->deleteJson("/api/v1/items/{$item->id}");

        $response->assertStatus(204); // Expect No Content

        $this->assertDatabaseMissing('items', ['id' => $item->id]);
    }

    public function test_item_delete_fails_for_non_existent_item(): void
    {
        $user = User::factory()->create();
        $response = $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/v1/items/99999");

        $response->assertStatus(404);
    }
}
