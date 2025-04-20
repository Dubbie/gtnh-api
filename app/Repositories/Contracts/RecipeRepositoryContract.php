<?php

namespace App\Repositories\Contracts;

use App\Models\Recipe;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface RecipeRepositoryContract
{
    public function getPaginated(int $perPage = 15, array $with = []): LengthAwarePaginator;
    public function findById(int $id, array $with = []): ?Recipe;
    public function create(array $data): Recipe;
    public function update(int $id, array $data): bool;
    public function delete(int $id): bool;
}
