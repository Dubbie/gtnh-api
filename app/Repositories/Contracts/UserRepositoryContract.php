<?php

namespace App\Repositories\Contracts;

use App\Models\User;

interface UserRepositoryContract
{
    public function findById(int $id): ?User;
    public function getUserPreferencesMap(int $userId): array;
}
