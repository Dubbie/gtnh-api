<?php

namespace App\Repositories\Eloquent;

use App\Models\Item;
use App\Repositories\Contracts\ItemRepositoryContract;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;
use Spatie\QueryBuilder\QueryBuilder;

class EloquentItemRepository implements ItemRepositoryContract
{
    public  function getAllPaginated(int $perPage = 15): LengthAwarePaginator
    {
        return QueryBuilder::for(Item::class)
            ->allowedFilters(['name', 'is_raw_material'])
            ->allowedSorts(['name',  'created_at', 'updated_at'])
            ->paginate($perPage)
            ->appends(request()->query());
    }

    public function findById(int $id): ?Item
    {
        return Item::find($id);
    }

    public function create(array $data): Item
    {
        return Item::create($data);
    }

    public function update(int $id, array $data): bool
    {
        $item = $this->findById($id);
        if (!$item) {
            return false;
        }

        $success = $item->update($data);
        Log::info('Item updated', ['id' => $id, 'data' => $data]);
        return $success;
    }

    public function delete(int $id): bool
    {
        $item = $this->findById($id);
        if (!$item) {
            return false;
        }
        return $item->delete();
    }
}
