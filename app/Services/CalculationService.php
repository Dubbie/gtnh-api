<?php

namespace App\Services;

// Import Repositories contracts
use App\Repositories\Contracts\ItemRepositoryContract;
use App\Repositories\Contracts\RecipeRepositoryContract;
use App\Repositories\Contracts\UserRepositoryContract;

// Import Models only for Type Hinting if needed, not direct querying
use App\Models\User;
use App\Exceptions\CalculationException; // Create this custom exception
use App\Exceptions\ItemNotFoundException; // Create this custom exception

use Illuminate\Support\Facades\Log;
use Throwable;

class CalculationService
{
    private const CHANCE_DIVISOR = 10000.0;

    // Dependencies
    protected ItemRepositoryContract $itemRepository;
    protected RecipeRepositoryContract $recipeRepository;
    protected UserRepositoryContract $userRepository;

    // State properties
    private array $calculationCache = []; // Consider if caching is needed across requests or just per-run
    private array $rawMaterialTotals = [];
    private array $userPreferences = [];

    public function __construct(
        ItemRepositoryContract $itemRepository,
        RecipeRepositoryContract $recipeRepository,
        UserRepositoryContract $userRepository
    ) {
        $this->itemRepository = $itemRepository;
        $this->recipeRepository = $recipeRepository;
        $this->userRepository = $userRepository;
    }

    /**
     * Public entry point to start the calculation.
     *
     * @param int $targetItemId The ID of the final item desired.
     * @param float $targetQuantity The quantity of the final item desired.
     * @param User|null $user Optional authenticated user for preferences.
     * @return array An array containing 'calculation_request', 'detailed_breakdown', and 'aggregated_raw_materials'.
     * @throws ItemNotFoundException|CalculationException|Throwable
     */
    public function calculateRequirements(int $targetItemId, float $targetQuantity, ?User $user = null): array
    {
        Log::info('Starting calculation requirements.', compact('targetItemId', 'targetQuantity'));

        $this->resetCalculationState();

        if ($user) {
            $this->userPreferences = $this->userRepository->getUserPreferencesMap($user->id);
            Log::debug('Loaded user preferences.', ['userId' => $user->id, 'prefsCount' => count($this->userPreferences)]);
        }

        try {
            $targetItem = $this->itemRepository->findById($targetItemId);
            if (!$targetItem) {
                Log::error("Target item not found.", compact('targetItemId'));
                throw new ItemNotFoundException("Target item with ID {$targetItemId} not found.");
            }
            if ($targetQuantity <= 0) {
                Log::error("Invalid target quantity provided.", compact('targetQuantity'));
                throw new CalculationException("Target quantity must be positive.");
            }

            // Start recursive calculation
            $detailedBreakdown = $this->getOrCreateCraftingSteps($targetItemId, $targetQuantity);

            // Check for errors propagated up from recursion
            if (isset($detailedBreakdown['error'])) {
                Log::error("Calculation tree building failed.", compact('targetItemId') + ['error' => $detailedBreakdown['error']]);
                throw new CalculationException('Calculation failed: ' . $detailedBreakdown['error']);
            }

            // Aggregate raw materials from the completed tree
            $this->aggregateRawMaterialsFromTree($detailedBreakdown);

            Log::info('Calculation completed successfully.', [
                'targetItemId' => $targetItemId,
                'targetQuantity' => $targetQuantity,
                'rawMaterialCount' => count($this->rawMaterialTotals)
            ]);

            return [
                'calculation_request' => [
                    'item_id' => $targetItemId,
                    'item_name' => $targetItem->name,
                    'quantity' => $targetQuantity,
                ],
                'detailed_breakdown' => $detailedBreakdown,
                'aggregated_raw_materials' => collect($this->rawMaterialTotals)
                    ->sortBy('name')
                    ->values()
                    ->all(),
            ];
        } catch (ItemNotFoundException | CalculationException $e) {
            // Log known calculation errors specifically
            Log::warning("Calculation failed: " . $e->getMessage(), [
                'targetItemId' => $targetItemId,
                'targetQuantity' => $targetQuantity,
                'exceptionType' => get_class($e)
            ]);
            throw $e; // Re-throw known exceptions for the handler
        } catch (Throwable $e) {
            Log::critical('Unexpected error during calculation requirements.', compact('targetItemId', 'targetQuantity') + [
                'message' => $e->getMessage(),
                'exception' => $e
            ]);
            // Wrap unexpected errors in a generic calculation exception
            throw new CalculationException('An unexpected error occurred during calculation.', 0, $e);
        }
    }

    /** Reset state for a new calculation run */
    private function resetCalculationState(): void
    {
        $this->calculationCache = [];
        $this->rawMaterialTotals = [];
        $this->userPreferences = [];
    }

    /**
     * Recursive function to get crafting steps.
     * Uses repositories for data fetching.
     *
     * @throws ItemNotFoundException|CalculationException
     */
    private function getOrCreateCraftingSteps(int $itemId, float $requiredQuantity): array
    {
        $item = $this->itemRepository->findById($itemId);
        if (!$item) {
            Log::warning("Item not found during recursion.", compact('itemId'));
            // Don't return error array, throw exception for consistency
            throw new ItemNotFoundException("Item ID {$itemId} not found during calculation.");
        }

        // Base Case: Raw Material
        if ($item->is_raw_material) {
            return [
                'item_id' => $itemId,
                'item_name' => $item->name,
                'is_raw' => true,
                'required_quantity' => $requiredQuantity,
            ];
        }

        // Recursive Case: Craftable Item

        // 1. Find Candidate Recipes (using Repository)
        $relationsToLoad = [
            // Load only the target output initially for selection
            'outputs' => fn($q) => $q->where('item_id', $itemId)->with('item:id,name'),
            // Load inputs and their items later *after* recipe selection for efficiency
            // 'inputs.inputItem:id,name,is_raw_material', // Defer loading
            'craftingMethod:id,name'
        ];
        $candidateRecipes = $this->recipeRepository->findRecipesProducingItem($itemId, $relationsToLoad);

        if ($candidateRecipes->isEmpty()) {
            Log::warning("No recipe found for non-raw item. Treating as raw.", [
                'itemId' => $itemId,
                'itemName' => $item->name
            ]);
            return [
                'item_id' => $itemId,
                'item_name' => $item->name,
                'is_raw' => true, // Treat as raw
                'required_quantity' => $requiredQuantity,
                'warning' => "No recipe found to produce this item.",
            ];
        }

        // 2. Select Recipe (User Pref -> Default -> First Found)
        $chosenRecipe = $this->selectRecipe($itemId, $candidateRecipes);

        // 3. Calculate Cycles Needed
        $targetOutputDetails = $chosenRecipe->outputs->firstWhere('item_id', $itemId); // Already loaded
        if (!$targetOutputDetails || $targetOutputDetails->quantity <= 0) { // Chance check done later for avg yield
            Log::error("Invalid output quantity details in chosen recipe.", ['recipeId' => $chosenRecipe->id, 'itemId' => $itemId]);
            throw new CalculationException("Invalid recipe output data for item ID {$itemId} in recipe {$chosenRecipe->id}.");
        }

        $avgYield = $targetOutputDetails->quantity * (max(0, $targetOutputDetails->chance) / self::CHANCE_DIVISOR); // Ensure chance isn't negative
        if ($avgYield <= 1e-9) { // Avoid division by zero or near-zero yield
            Log::warning("Recipe has zero or near-zero average yield for target item.", ['recipeId' => $chosenRecipe->id, 'itemId' => $itemId]);
            // Decide how to handle: throw error or treat as impossible? Let's throw.
            throw new CalculationException("Recipe {$chosenRecipe->id} has zero average yield for item ID {$itemId}. Cannot calculate cycles.");
        }
        $cyclesNeeded = ceil($requiredQuantity / $avgYield);

        // --- Load remaining relations for chosen recipe ---
        $chosenRecipe->loadMissing(['inputs.inputItem:id,name,is_raw_material', 'outputs.item:id,name']);


        // 4. Build Current Node
        $currentNode = [
            'item_id' => $itemId,
            'item_name' => $item->name,
            'required_quantity' => $requiredQuantity,
            'recipe_used' => [
                'id' => $chosenRecipe->id,
                'name' => $chosenRecipe->name ?? "Recipe #{$chosenRecipe->id}",
                'method_name' => $chosenRecipe->craftingMethod->name ?? 'Unknown',
                'target_output_qty_per_cycle' => $targetOutputDetails->quantity,
                'target_output_chance' => $targetOutputDetails->chance,
                'avg_yield_per_cycle' => $avgYield,
            ],
            'cycles_needed' => $cyclesNeeded,
            'total_produced_avg' => $avgYield * $cyclesNeeded,
            'is_raw' => false,
            'direct_inputs' => [],
            'expected_outputs' => [],
            'sub_steps' => [], // Initialize sub-steps
        ];

        // 5. Process Inputs Recursively
        foreach ($chosenRecipe->inputs as $input) {
            if (!$input->relationLoaded('inputItem') || !$input->inputItem) {
                Log::error("Input item relation not loaded.", ['input_id' => $input->id, 'recipe_id' => $chosenRecipe->id]);
                throw new CalculationException("Failed to load input item details for recipe {$chosenRecipe->id}.");
            }
            $inputTotalQuantity = $input->input_quantity * $cyclesNeeded;

            $currentNode['direct_inputs'][] = [
                'item_id' => $input->input_item_id,
                'item_name' => $input->inputItem->name,
                'quantity_per_cycle' => $input->input_quantity,
                'total_required_for_this_step' => $inputTotalQuantity,
            ];

            // Recursive call - wrap in try-catch to handle errors from sub-steps
            try {
                $subStepNode = $this->getOrCreateCraftingSteps($input->input_item_id, $inputTotalQuantity);
                $currentNode['sub_steps'][] = $subStepNode;
            } catch (ItemNotFoundException | CalculationException $e) {
                // Log and re-throw to propagate the failure
                Log::error("Error in sub-step calculation.", ['parentItemId' => $itemId, 'inputItemId' => $input->input_item_id, 'error' => $e->getMessage()]);
                throw new CalculationException("Calculation failed for input '{$input->inputItem->name}': " . $e->getMessage(), 0, $e);
            }
        }

        // 6. Calculate Expected Outputs (using loaded relations)
        foreach ($chosenRecipe->outputs as $output) {
            if (!$output->relationLoaded('item') || !$output->item) {
                Log::warning("Output item relation not loaded.", ['output_id' => $output->id, 'recipe_id' => $chosenRecipe->id]);
                continue; // Skip if item fails to load for some reason
            }
            $expectedQuantity = $output->quantity * (max(0, $output->chance) / self::CHANCE_DIVISOR) * $cyclesNeeded;
            $currentNode['expected_outputs'][] = [
                'item_id' => $output->item_id,
                'item_name' => $output->item->name,
                'quantity_per_cycle' => $output->quantity,
                'chance' => $output->chance,
                'is_primary' => $output->is_primary_output,
                'total_expected_yield' => $expectedQuantity,
            ];
        }

        return $currentNode;
    }

    /** Selects the recipe based on user preference, default flag, or fallback */
    private function selectRecipe(int $itemId, \Illuminate\Database\Eloquent\Collection $candidateRecipes): \App\Models\Recipe
    {
        $chosenRecipe = null;

        // A. Check User Preference
        if (!empty($this->userPreferences) && isset($this->userPreferences[$itemId])) {
            $preferredRecipeId = $this->userPreferences[$itemId];
            $chosenRecipe = $candidateRecipes->firstWhere('id', $preferredRecipeId);
            if ($chosenRecipe) {
                Log::debug('Using user preferred recipe.', compact('itemId', 'preferredRecipeId'));
                return $chosenRecipe;
            } else {
                Log::warning('User preferred recipe not found among candidates.', compact('itemId', 'preferredRecipeId'));
            }
        }

        // B. Fallback to Default Flag
        $defaultRecipe = $candidateRecipes->firstWhere('is_default', true);
        if ($defaultRecipe) {
            Log::debug('Using default recipe (is_default=true).', ['itemId' => $itemId, 'recipeId' => $defaultRecipe->id]);
            return $defaultRecipe;
        }

        // C. Fallback to First Found (with warning if multiple exist)
        if ($candidateRecipes->count() > 1) {
            Log::warning("Multiple recipes found, no user preference or default set. Using first found.", [
                'itemId' => $itemId,
                'recipeIds' => $candidateRecipes->pluck('id')->all()
            ]);
            // Consider throwing CalculationException here if ambiguity is unacceptable
            // throw new CalculationException("Ambiguous choice: Multiple recipes found for item ID {$itemId}. Please set a preference or mark one as default.");
        }
        $chosenRecipe = $candidateRecipes->first();
        Log::debug('Using first found recipe as default.', ['itemId' => $itemId, 'recipeId' => $chosenRecipe->id]);
        return $chosenRecipe;
    }


    /** Helper to aggregate raw materials by traversing the calculated tree */
    private function aggregateRawMaterialsFromTree(array $node): void
    {
        // Use nullish coalescing for safer access
        $isRaw = $node['is_raw'] ?? false;
        $warning = $node['warning'] ?? null;
        $itemId = $node['item_id'] ?? null;
        $itemName = $node['item_name'] ?? 'Unknown Item';
        $quantity = $node['required_quantity'] ?? null;

        // Base Case 1: Explicitly Raw
        if ($isRaw === true) {
            if ($itemId && $quantity !== null) {
                $this->addRawMaterial($itemId, $itemName, $quantity);
            } else {
                Log::warning('Raw node missing ID or quantity during aggregation.', compact('node'));
            }
            return;
        }

        // Base Case 2: Treated as Raw due to Warning
        if ($warning && str_contains($warning, 'No recipe found')) {
            if ($itemId && $quantity !== null) {
                $this->addRawMaterial($itemId, $itemName, $quantity);
            } else {
                Log::warning('Warning node missing ID or quantity during aggregation.', compact('node'));
            }
            return;
        }

        // Skip Error Nodes
        if (isset($node['error'])) {
            return;
        }

        // Recursive Case: Process sub-steps
        if (!empty($node['sub_steps']) && is_array($node['sub_steps'])) {
            foreach ($node['sub_steps'] as $subStep) {
                if (is_array($subStep)) {
                    $this->aggregateRawMaterialsFromTree($subStep);
                }
            }
        }
    }

    /** Adds a quantity to the raw material totals */
    private function addRawMaterial(int $itemId, string $itemName, float $quantity): void
    {
        if ($quantity <= 0) return;

        if (!isset($this->rawMaterialTotals[$itemId])) {
            $this->rawMaterialTotals[$itemId] = [
                'item_id' => $itemId,
                'name' => $itemName,
                'total_quantity' => 0.0,
            ];
        }
        $this->rawMaterialTotals[$itemId]['total_quantity'] += $quantity;
    }
}
