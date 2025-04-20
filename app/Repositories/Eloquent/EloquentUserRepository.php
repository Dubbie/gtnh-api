<?php

namespace App\Repositories\Eloquent;

use App\Models\User;
use App\Repositories\Contracts\UserRepositoryContract;

class EloquentUserRepository implements UserRepositoryContract
{
    public function findById(int $id): ?User
    {
        return User::find($id);
    }

    public function getUserPreferencesMap(int $userId): array
    {
        $user = $this->findById($userId);
        if (!$user) {
            return [];
        }

        // Use the relationship defined on the User model
        return $user->preferredRecipes()
            ->pluck('preferred_recipe_id', 'output_item_id')
            ->all();
    }
}
