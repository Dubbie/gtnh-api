<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CalculationController;
use App\Http\Controllers\Api\V1\CraftingMethodController;
use App\Http\Controllers\Api\V1\ItemController;
use App\Http\Controllers\Api\V1\RecipeController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::prefix('v1')->group(function () {
    // Authentication Routes
    Route::post('/login', [AuthController::class, 'login'])->name('auth.login');

    // Routes Requiring Authentication
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout'])->name('auth.logout');
        Route::get('/user', [AuthController::class, 'user'])->name('auth.user');

        // Protect all write/modify operations
        Route::resource('items', ItemController::class)->except(['index', 'show']);
        Route::resource('crafting-methods', CraftingMethodController::class)->except(['index', 'show']);
        Route::resource('recipes', RecipeController::class)->except(['index', 'show']);
    });

    // Public routes
    Route::resource('items', ItemController::class)->only(['index', 'show']);
    Route::resource('crafting-methods', CraftingMethodController::class)->only(['index', 'show']);
    Route::resource('recipes', RecipeController::class)->only(['index', 'show']);
    Route::post('/calculations', CalculationController::class)->name('calculations.store');

    // Fallback route
    Route::fallback(function () {
        return response()->json(['error' => 'Not found'], 404);
    });
});
