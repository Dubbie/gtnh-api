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
        Schema::create('recipes', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            // Foreign key for the method used
            $table->foreignId('crafting_method_id')->constrained('crafting_methods')->cascadeOnDelete();
            $table->unsignedInteger('eu_per_tick')->nullable();
            $table->unsignedInteger('duration_ticks')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_default')->default(false)->index(); // Index for potential default lookups
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recipes');
    }
};
