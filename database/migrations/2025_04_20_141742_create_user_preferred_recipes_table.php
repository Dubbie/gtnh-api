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
        Schema::create('user_preferred_recipes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            // The item the user is setting a preference for
            $table->foreignId('output_item_id')->constrained('items')->cascadeOnDelete();
            // The specific recipe the user prefers for making that item
            $table->foreignId('preferred_recipe_id')->constrained('recipes')->cascadeOnDelete();
            $table->timestamps();

            // A user can only have one preferred recipe for a given output item
            $table->unique(['user_id', 'output_item_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_preferred_recipes');
    }
};
