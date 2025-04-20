<?php

namespace Tests\Unit\Repositories;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use App\Models\Item;
use App\Models\Recipe;
use App\Models\UserPreferredRecipe;
use App\Models\CraftingMethod;
use App\Repositories\Contracts\UserRepositoryContract;
use App\Repositories\Eloquent\EloquentUserRepository;
use PHPUnit\Framework\Attributes\Test;

class EloquentUserRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private UserRepositoryContract $repository;

    protected function setUp(): void
    {
        parent::setUp();
        // Resolve the repository implementation from the container
        $this->repository = $this->app->make(UserRepositoryContract::class);
        // Ensure the binding is correct in RepositoryServiceProvider
        $this->assertInstanceOf(EloquentUserRepository::class, $this->repository);
    }

    #[Test]
    public function find_by_id_returns_user_when_exists(): void
    {
        $user = User::factory()->create();

        $foundUser = $this->repository->findById($user->id);

        $this->assertInstanceOf(User::class, $foundUser);
        $this->assertEquals($user->id, $foundUser->id);
        $this->assertEquals($user->email, $foundUser->email);
    }

    #[Test]
    public function find_by_id_returns_null_when_not_exists(): void
    {
        $foundUser = $this->repository->findById(9999); // Non-existent ID
        $this->assertNull($foundUser);
    }

    #[Test]
    public function get_user_preferences_map_returns_empty_array_for_user_with_no_preferences(): void
    {
        $user = User::factory()->create();
        $prefs = $this->repository->getUserPreferencesMap($user->id);
        $this->assertIsArray($prefs);
        $this->assertEmpty($prefs);
    }

    #[Test]
    public function get_user_preferences_map_returns_correct_map_for_user_with_preferences(): void
    {
        $user = User::factory()->create();
        $item1 = Item::factory()->create();
        $item2 = Item::factory()->create();
        $method = CraftingMethod::factory()->create(); // Need method for recipe
        $recipe1 = Recipe::factory()->create(['crafting_method_id' => $method->id]);
        $recipe2 = Recipe::factory()->create(['crafting_method_id' => $method->id]);

        // Create preferences using the factory
        UserPreferredRecipe::factory()->create([
            'user_id' => $user->id,
            'output_item_id' => $item1->id,
            'preferred_recipe_id' => $recipe1->id,
        ]);
        UserPreferredRecipe::factory()->create([
            'user_id' => $user->id,
            'output_item_id' => $item2->id,
            'preferred_recipe_id' => $recipe2->id,
        ]);

        $prefs = $this->repository->getUserPreferencesMap($user->id);

        $this->assertIsArray($prefs);
        $this->assertCount(2, $prefs);
        $this->assertArrayHasKey($item1->id, $prefs);
        $this->assertEquals($recipe1->id, $prefs[$item1->id]);
        $this->assertArrayHasKey($item2->id, $prefs);
        $this->assertEquals($recipe2->id, $prefs[$item2->id]);
    }

    #[Test]
    public function get_user_preferences_map_returns_empty_array_for_non_existent_user(): void
    {
        $prefs = $this->repository->getUserPreferencesMap(9999);
        $this->assertIsArray($prefs);
        $this->assertEmpty($prefs);
    }
}
