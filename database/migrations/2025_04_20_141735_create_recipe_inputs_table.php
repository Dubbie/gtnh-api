<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('recipe_inputs', function (Blueprint $table) {
            $table->id();
            // Foreign key linking to the recipe this input belongs to
            $table->foreignId('recipe_id')->constrained('recipes')->cascadeOnDelete();
            // Foreign key linking to the item required as input
            $table->foreignId('input_item_id')->constrained('items')->cascadeOnDelete();
            $table->unsignedInteger('input_quantity');

            // Ensure an item isn't listed as an input twice for the same recipe
            $table->unique(['recipe_id', 'input_item_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recipe_inputs');
    }
};
