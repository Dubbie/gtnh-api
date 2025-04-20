<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ListRecipesRequest;
use App\Http\Requests\StoreRecipeRequest;
use App\Http\Requests\UpdateRecipeRequest;
use App\Http\Resources\RecipeResource;
use App\Services\RecipeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class RecipeController extends Controller
{
    protected RecipeService $recipeService;

    public function __construct(RecipeService $recipeService)
    {
        $this->recipeService = $recipeService;
    }

    /**
     * Index
     *
     * Returns a paginated list of recipes
     */
    public function index(ListRecipesRequest $request): AnonymousResourceCollection
    {
        $perPage = $request->input('per_page', 15);
        $recipes = $this->recipeService->getRecipes($perPage);

        return RecipeResource::collection($recipes);
    }

    /**
     * Store
     *
     * Stores a newly created recipe in storage.
     */
    public function store(StoreRecipeRequest $request): JsonResponse
    {
        $recipe = $this->recipeService->createRecipe($request->validated());

        return (new RecipeResource($recipe))->response()->setStatusCode(201);
    }

    /**
     * Show
     *
     * Display the specified recipe.
     */
    public function show(int $id): RecipeResource
    {
        $recipe = $this->recipeService->findRecipeById($id);
        return new RecipeResource($recipe);
    }

    /**
     * Update
     *
     * Updates the specified recipe in storage.
     */
    public function update(UpdateRecipeRequest $request, int $id): RecipeResource
    {
        $recipe = $this->recipeService->updateRecipe($id, $request->validated());
        return new RecipeResource($recipe);
    }

    /**
     * Destroy
     *
     * Removes the specified recipe from storage.
     */
    public function destroy(int $id): JsonResponse
    {
        $this->recipeService->deleteRecipe($id);
        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
