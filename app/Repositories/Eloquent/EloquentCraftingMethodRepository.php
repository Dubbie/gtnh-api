<?php

namespace App\Repositories\Eloquent;

use App\Models\CraftingMethod;
use App\Repositories\Contracts\CraftingMethodRepositoryContract;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;
use Spatie\QueryBuilder\QueryBuilder;

class EloquentCraftingMethodRepository implements CraftingMethodRepositoryContract
{
    public  function getAllPaginated(int $perPage = 15): LengthAwarePaginator
    {
        return QueryBuilder::for(CraftingMethod::class)
            ->allowedFilters(['name'])
            ->allowedSorts(['name',  'created_at', 'updated_at'])
            ->paginate($perPage)
            ->appends(request()->query());
    }

    public function findById(int $id): ?CraftingMethod
    {
        return CraftingMethod::find($id);
    }

    public function create(array $data): CraftingMethod
    {
        return CraftingMethod::create($data);
    }

    public function update(int $id, array $data): bool
    {
        $craftingMethod = $this->findById($id);
        if (!$craftingMethod) {
            return false;
        }

        $success = $craftingMethod->update($data);
        Log::info('Crafting method updated', ['id' => $id, 'data' => $data]);
        return $success;
    }

    public function delete(int $id): bool
    {
        $craftingMethod = $this->findById($id);
        if (!$craftingMethod) {
            return false;
        }
        return $craftingMethod->delete();
    }
}
