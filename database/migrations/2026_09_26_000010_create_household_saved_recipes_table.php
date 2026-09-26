<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Another household's published recipe, kept in this household's collection to read and cook
 * from. A bookmark, not a copy: the recipe stays its owners', and the saving household sees
 * whatever they publish next. Changing it means making a version of your own (a fork).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('household_saved_recipes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('household_id')->constrained()->cascadeOnDelete();
            $table->foreignId('recipe_id')->constrained()->cascadeOnDelete();
            $table->foreignId('saved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['household_id', 'recipe_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('household_saved_recipes');
    }
};
