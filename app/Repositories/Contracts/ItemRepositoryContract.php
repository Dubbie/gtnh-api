<?php

namespace App\Repositories\Contracts;

use App\Models\Item;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface ItemRepositoryContract
{
    public function getPaginated(int $perPage = 15): LengthAwarePaginator;
    public function findById(int $id): ?Item;
    public function create(array $data): Item;
    public function update(int $id, array $data): bool;
    public function delete(int $id): bool;
}
