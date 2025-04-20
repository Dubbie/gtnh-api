<?php

namespace App\Repositories\Contracts;

use App\Models\CraftingMethod;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface CraftingMethodRepositoryContract
{
    public function getPaginated(int $perPage = 15): LengthAwarePaginator;
    public function findById(int $id): ?CraftingMethod;
    public function create(array $data): CraftingMethod;
    public function update(int $id, array $data): bool;
    public function delete(int $id): bool;
}
