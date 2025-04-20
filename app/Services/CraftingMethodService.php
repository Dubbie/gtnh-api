<?php

namespace App\Services;

use App\Models\CraftingMethod;
use App\Repositories\Contracts\CraftingMethodRepositoryContract;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class CraftingMethodService
{
    protected CraftingMethodRepositoryContract $craftingMethodRepository;

    public function __construct(CraftingMethodRepositoryContract $craftingMethodRepository)
    {
        $this->craftingMethodRepository = $craftingMethodRepository;
    }

    public function getCraftingMethods(int $perPage = 15): LengthAwarePaginator
    {
        return $this->craftingMethodRepository->getAllPaginated($perPage);
    }

    public function findCraftingMethodById(int $id): CraftingMethod
    {
        $craftingMethod = $this->craftingMethodRepository->findById($id);
        if (!$craftingMethod) {
            throw new ModelNotFoundException("CraftingMethod with ID {$id} not found.");
        }
        return $craftingMethod;
    }

    public function createCraftingMethod(array $data): CraftingMethod
    {
        return $this->craftingMethodRepository->create($data);
    }

    public function updateCraftingMethod(int $id, array $data): CraftingMethod
    {
        $updated = $this->craftingMethodRepository->update($id, $data);
        if (!$updated) {
            throw new ModelNotFoundException("CraftingMethod with ID {$id} not found.");
        }

        // Re-fetch the updated model
        return $this->findCraftingMethodById($id);
    }

    public function deleteCraftingMethod(int $id): bool
    {
        $deleted = $this->craftingMethodRepository->delete($id);
        if (!$deleted) {
            throw new ModelNotFoundException("CraftingMethod with ID {$id} not found.");
        }
        return true;
    }
}
