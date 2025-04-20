<?php

namespace App\Providers;

use App\Repositories\Contracts\CraftingMethodRepositoryContract;
use App\Repositories\Contracts\ItemRepositoryContract;
use App\Repositories\Eloquent\EloquentCraftingMethodRepository;
use App\Repositories\Eloquent\EloquentItemRepository;
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
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
