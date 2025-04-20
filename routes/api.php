<?php

use App\Http\Controllers\Api\V1\CraftingMethodController;
use App\Http\Controllers\Api\V1\ItemController;
use App\Http\Controllers\Api\V1\RecipeController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::prefix('v1')->group(function () {
    Route::resource('items', ItemController::class);
    Route::resource('crafting-methods', CraftingMethodController::class);
    Route::resource('recipes', RecipeController::class);
});
