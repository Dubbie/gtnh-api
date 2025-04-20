<?php

namespace App\Repositories\Eloquent;

use App\Models\Recipe;
use App\Repositories\Contracts\RecipeRepositoryContract;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Spatie\QueryBuilder\QueryBuilder;

class EloquentRecipeRepository implements RecipeRepositoryContract
{
    public function getPaginated(int $perPage = 15, array $with = []): LengthAwarePaginator
    {
        // Define default relations if needed, or rely on $with argument
        $query = Recipe::query()->with($with);

        return QueryBuilder::for($query)
            ->allowedFilters([ // Define filters allowed via query params (?filter[name]=...)
                'name',
                'crafting_method_id',
                'is_default',
                // Add filters for relationships if needed (e.g., 'outputs.item.slug')
            ])
            ->allowedIncludes([ // Allow including relations via ?include=...
                'craftingMethod',
                'inputs',
                'inputs.inputItem',
                'outputs',
                'outputs.item',
                'primaryOutput',
                'primaryOutput.item', // For index primary output
            ])
            ->allowedSorts([ // Define allowed sorts (?sort=name, -created_at)
                'name',
                'eu_per_tick',
                'duration_ticks',
                'created_at',
                'updated_at',
            ])
            ->paginate($perPage)
            ->appends(request()->query());
    }

    public function findById(int $id, array $with = []): ?Recipe
    {
        // Eager load relations passed in $with
        return Recipe::with($with)->find($id);
    }

    public function create(array $data): Recipe
    {
        return Recipe::create($data);
    }

    public function update(int $id, array $data): bool
    {
        $recipe = $this->findById($id);
        if (!$recipe) {
            return false;
        }
        return $recipe->update($data);
    }

    public function delete(int $id): bool
    {
        $recipe = Recipe::find($id);
        if (!$recipe) {
            return false;
        }
        return $recipe->delete();
    }

    public function findRecipesProducingItem(int $outputItemId, array $with = []): Collection
    {
        return Recipe::whereHas('outputs', function ($query) use ($outputItemId) {
            $query->where('item_id', $outputItemId);
        })->with($with)->get();
    }
}
