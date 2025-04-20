<?php

namespace App\Services;

use App\Models\Item;
use App\Repositories\Contracts\ItemRepositoryContract;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class ItemService
{
    protected ItemRepositoryContract $itemRepository;

    public function __construct(ItemRepositoryContract $itemRepository)
    {
        $this->itemRepository = $itemRepository;
    }

    public function getItems(int $perPage = 15): LengthAwarePaginator
    {
        return $this->itemRepository->getPaginated($perPage);
    }

    public function findItemById(int $id): Item
    {
        $item = $this->itemRepository->findById($id);
        if (!$item) {
            throw new ModelNotFoundException("Item with ID {$id} not found.");
        }
        return $item;
    }

    public function createItem(array $data): Item
    {
        return $this->itemRepository->create($data);
    }

    public function updateItem(int $id, array $data): Item
    {
        $updated = $this->itemRepository->update($id, $data);
        if (!$updated) {
            throw new ModelNotFoundException("Item with ID {$id} not found.");
        }

        // Re-fetch the updated model
        return $this->findItemById($id);
    }

    public function deleteItem(int $id): bool
    {
        $deleted = $this->itemRepository->delete($id);
        if (!$deleted) {
            throw new ModelNotFoundException("Item with ID {$id} not found.");
        }
        return true;
    }
}
