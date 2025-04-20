<?php

namespace App\Providers;

use App\Repositories\Contracts\CraftingMethodRepositoryContract;
use App\Repositories\Contracts\ItemRepositoryContract;
use App\Repositories\Contracts\RecipeRepositoryContract;
use App\Repositories\Contracts\UserRepositoryContract;
use App\Repositories\Eloquent\EloquentCraftingMethodRepository;
use App\Repositories\Eloquent\EloquentItemRepository;
use App\Repositories\Eloquent\EloquentRecipeRepository;
use App\Repositories\Eloquent\EloquentUserRepository;
use Illuminate\Support\ServiceProvider;

class RepositoryServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->bind(ItemRepositoryContract::class, EloquentItemRepository::class);
        $this->app->bind(CraftingMethodRepositoryContract::class, EloquentCraftingMethodRepository::class);
        $this->app->bind(RecipeRepositoryContract::class, EloquentRecipeRepository::class);
        $this->app->bind(UserRepositoryContract::class, EloquentUserRepository::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
