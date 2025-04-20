<?php

namespace Tests\Feature;

use App\Models\CraftingMethod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CraftingMethodApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    public function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    // === GET /api/v1/crafting-methods (Index) ===
    public function test_can_get_list_of_crafting_methods(): void
    {
        CraftingMethod::factory()->count(3)->create();
        $response = $this->getJson('/api/v1/crafting-methods');
        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => ['*' => ['id', 'type', 'attributes' => ['name', 'slug'], 'links']],
                'links',
                'meta'
            ])
            ->assertJsonCount(3, 'data');
    }

    public function test_crafting_methods_list_respects_per_page(): void
    {
        CraftingMethod::factory()->count(5)->create();
        $response = $this->getJson('/api/v1/crafting-methods?per_page=2');
        $response->assertStatus(200)->assertJsonCount(2, 'data')->assertJsonPath('meta.per_page', 2);
    }

    // === GET /api/v1/crafting-methods/{method} (Show) ===
    public function test_can_get_single_crafting_method(): void
    {
        $method = CraftingMethod::factory()->create();
        $response = $this->getJson("/api/v1/crafting-methods/{$method->id}");
        $response->assertStatus(200)
            ->assertJsonPath('data.id', $method->id)
            ->assertJsonPath('data.attributes.name', $method->name);
    }

    public function test_returns_404_for_non_existent_crafting_method(): void
    {
        $response = $this->getJson('/api/v1/crafting-methods/9999');
        $response->assertStatus(404);
    }

    // === POST /api/v1/crafting-methods (Store) ===
    public function test_guest_cannot_create_crafting_methods(): void
    {
        $response = $this->postJson('/api/v1/crafting-methods', ['name' => 'Test Mixer']);
        $response->assertStatus(401);
        $this->assertDatabaseMissing('crafting_methods', ['name' => 'Test Mixer']);
    }

    public function test_authenticated_user_can_create_crafting_methods(): void
    {
        $data = ['name' => 'Test Mixer', 'description' => 'Mixes things.'];

        $response = $this->actingAs($this->user, 'sanctum')->postJson('/api/v1/crafting-methods', $data);

        $response->assertStatus(201)
            ->assertJsonPath('data.attributes.name', 'Test Mixer')
            ->assertJsonPath('data.attributes.description', 'Mixes things.');

        $this->assertDatabaseHas('crafting_methods', ['name' => 'Test Mixer', 'slug' => 'test-mixer']);
    }

    public function test_crafting_method_creation_fails_with_missing_name(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')->postJson('/api/v1/crafting-methods', ['description' => 'No name']);
        $response->assertStatus(422)->assertJsonValidationErrors(['name']);
    }

    public function test_crafting_method_creation_fails_with_duplicate_name(): void
    {
        CraftingMethod::factory()->create(['name' => 'Duplicate Method']);
        $response = $this->actingAs($this->user, 'sanctum')->postJson('/api/v1/crafting-methods', ['name' => 'Duplicate Method']);
        $response->assertStatus(422)->assertJsonValidationErrors(['name']);
    }

    // === PUT/PATCH /api/v1/crafting-methods/{method} (Update) ===
    public function test_guest_cannot_update_crafting_methods(): void
    {
        $method = CraftingMethod::factory()->create(['name' => 'Old Name']);

        $response = $this->putJson("/api/v1/crafting-methods/{$method->id}", ['name' => 'New Name']);

        $response->assertStatus(401);
        $this->assertDatabaseHas('crafting_methods', ['id' => $method->id, 'name' => 'Old Name']);
    }

    public function test_authenticated_user_can_update_crafting_methods(): void
    {
        $method = CraftingMethod::factory()->create(['name' => 'Old Name']);
        $updateData = ['name' => 'New Name', 'description' => 'Updated Desc'];

        $response = $this->actingAs($this->user, 'sanctum')->putJson("/api/v1/crafting-methods/{$method->id}", $updateData);

        $response->assertStatus(200)
            ->assertJsonPath('data.attributes.name', 'New Name')
            ->assertJsonPath('data.attributes.description', 'Updated Desc');
        $this->assertDatabaseHas('crafting_methods', ['id' => $method->id, 'name' => 'New Name']);
    }

    public function test_crafting_method_update_fails_for_non_existent_method(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')->putJson("/api/v1/crafting-methods/9999", ['name' => 'New Name']);
        $response->assertStatus(404);
    }

    // === DELETE /api/v1/crafting-methods/{method} (Destroy) ===
    public function test_guest_cannot_delete_crafting_methods(): void
    {
        $method = CraftingMethod::factory()->create();

        $response = $this->deleteJson("/api/v1/crafting-methods/{$method->id}");

        $response->assertStatus(401);
        $this->assertDatabaseHas('crafting_methods', ['id' => $method->id]);
    }

    public function test_authenticated_user_can_delete_crafting_methods(): void
    {
        $method = CraftingMethod::factory()->create();

        $response = $this->actingAs($this->user, 'sanctum')->deleteJson("/api/v1/crafting-methods/{$method->id}");

        $response->assertStatus(204);
        $this->assertDatabaseMissing('crafting_methods', ['id' => $method->id]);
    }

    public function test_crafting_method_delete_fails_for_non_existent_method(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')->deleteJson("/api/v1/crafting-methods/9999");
        $response->assertStatus(404);
    }
}
