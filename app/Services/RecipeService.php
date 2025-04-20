<?php

namespace App\Services;

use App\Models\Recipe;
use App\Repositories\Contracts\RecipeRepositoryContract;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class RecipeService
{
    protected $recipeRepository;

    // Inject dependencies
    public function __construct(RecipeRepositoryContract $recipeRepository)
    {
        $this->recipeRepository = $recipeRepository;
    }

    /**
     * Get paginated recipes, suitable for API index.
     */
    public function getRecipes(int $perPage = 15): LengthAwarePaginator
    {
        $relations = [
            'craftingMethod:id,name,slug',
            'primaryOutput.item:id,name,slug,image_url'
        ];
        return $this->recipeRepository->getPaginated($perPage, $relations);
    }

    /**
     * Find a single recipe by ID with detailed relations for API show.
     */
    public function findRecipeById(int $id): Recipe
    {
        // Define relations needed for the detailed show view / single resource
        $relations = [
            'craftingMethod',
            'inputs.inputItem:id,name,slug,image_url',
            'outputs.item:id,name,slug,image_url'
        ];
        $recipe = $this->recipeRepository->findById($id, $relations);

        if (!$recipe) {
            throw new ModelNotFoundException("Recipe with ID {$id} not found.");
        }
        return $recipe;
    }

    /**
     * Create a new recipe with its inputs and outputs.
     * Assumes $data is validated by StoreRecipeRequest.
     *
     * @throws Throwable
     */
    public function createRecipe(array $data): Recipe
    {
        // Separate recipe data from inputs/outputs
        $inputsData = $data['inputs'];
        $outputsData = $data['outputs'];
        unset($data['inputs'], $data['outputs']);

        DB::beginTransaction();
        try {
            // 1. Create main recipe record
            $recipe = $this->recipeRepository->create($data);

            // 2. Prepare and create inputs
            $mappedInputs = collect($inputsData)->map(fn($input) => [
                'input_item_id' => $input['input_item_id'],
                'input_quantity' => $input['input_quantity'],
            ])->all();
            if (!empty($mappedInputs)) {
                $recipe->inputs()->createMany($mappedInputs);
            }

            // 3. Prepare and create outputs
            $mappedOutputs = collect($outputsData)->map(fn($output) => [
                'item_id' => $output['item_id'],
                'quantity' => $output['quantity'],
                'chance' => $output['chance'],
                'is_primary_output' => $output['is_primary_output'],
            ])->all();
            if (!empty($mappedOutputs)) {
                $recipe->outputs()->createMany($mappedOutputs);
            }

            DB::commit();

            return $recipe->load([
                'craftingMethod',
                'inputs.inputItem',
                'outputs.item'
            ]);
        } catch (Throwable $e) {
            DB::rollBack();
            Log::error("Error creating recipe: " . $e->getMessage(), [
                'exception' => $e,
                'data' => $data
            ]);
            // Re-throw the exception to be handled by the Exception Handler
            throw $e;
        }
    }

    /**
     * Update an existing recipe and sync its inputs and outputs.
     * Assumes $data is validated by UpdateRecipeRequest.
     *
     * @throws Throwable
     */
    public function updateRecipe(int $id, array $data): Recipe
    {
        // Separate recipe data from inputs/outputs
        $inputsData = $data['inputs'];
        $outputsData = $data['outputs'];
        unset($data['inputs'], $data['outputs']);

        // Find recipe *before* transaction to fail early if not found
        $recipe = $this->recipeRepository->findById($id);
        if (!$recipe) {
            throw new ModelNotFoundException("Recipe with ID {$id} not found for update.");
        }

        DB::beginTransaction();
        try {
            // 1. Update main recipe record
            $this->recipeRepository->update($id, $data);

            // Reload the recipe instance to ensure we have fresh data after update
            $recipe->refresh();

            // --- 2. Sync Inputs ---
            $this->syncInputs($recipe, $inputsData);

            // --- 3. Sync Outputs ---
            $this->syncOutputs($recipe, $outputsData);

            DB::commit();

            // Return the updated recipe with necessary relations loaded
            return $recipe->load([
                'craftingMethod',
                'inputs.inputItem',
                'outputs.item'
            ]);
        } catch (Throwable $e) {
            DB::rollBack();
            Log::error("Error updating recipe {$id}: " . $e->getMessage(), [
                'exception' => $e,
                'data' => $data
            ]);
            // Re-throw the exception
            throw $e;
        }
    }

    /**
     * Delete a recipe.
     */
    public function deleteRecipe(int $id): bool
    {
        // Find recipe first to ensure it exists
        $recipe = $this->recipeRepository->findById($id);
        if (!$recipe) {
            throw new ModelNotFoundException("Recipe with ID {$id} not found for deletion.");
        }

        // Use transaction just in case model events or observers do complex things
        return DB::transaction(function () use ($id) {
            return $this->recipeRepository->delete($id);
        });
    }

    // --- Helper Sync Methods ---
    protected function syncInputs(Recipe $recipe, array $inputsData): void
    {
        $submittedInputs = collect($inputsData)->keyBy('input_item_id');
        $existingInputs = $recipe->inputs()->get()->keyBy('input_item_id');

        // IDs to keep/update
        $submittedInputItemIds = $submittedInputs->keys();

        // 1. Delete inputs no longer present
        $inputsToDelete = $existingInputs->whereNotIn('input_item_id', $submittedInputItemIds);
        if ($inputsToDelete->isNotEmpty()) {
            $recipe->inputs()->whereIn('id', $inputsToDelete->pluck('id'))->delete();
        }

        // 2. Update existing or Create new inputs
        foreach ($submittedInputs as $itemId => $inputData) {
            $recipe->inputs()->updateOrCreate(
                ['input_item_id' => $itemId],
                ['input_quantity' => $inputData['input_quantity']]
            );
        }
    }

    protected function syncOutputs(Recipe $recipe, array $outputsData): void
    {
        $submittedOutputs = collect($outputsData)->keyBy('item_id');
        $existingOutputs = $recipe->outputs()->get()->keyBy('item_id');

        // IDs to keep/update
        $submittedOutputItemIds = $submittedOutputs->keys();

        // 1. Delete outputs no longer present
        $outputsToDelete = $existingOutputs->whereNotIn('item_id', $submittedOutputItemIds);
        if ($outputsToDelete->isNotEmpty()) {
            $recipe->outputs()->whereIn('id', $outputsToDelete->pluck('id'))->delete();
        }

        // 2. Update existing or Create new outputs
        foreach ($submittedOutputs as $itemId => $outputData) {
            $recipe->outputs()->updateOrCreate(
                ['item_id' => $itemId],
                [
                    'quantity' => $outputData['quantity'],
                    'chance' => $outputData['chance'],
                    'is_primary_output' => $outputData['is_primary_output'],
                ]
            );
        }
    }
}
