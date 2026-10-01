<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A household's list of what it means to cook: each row points at a recipe, either one it
 * already has (or saved) or one started from just a dish name. A day is optional — the list
 * works as "coming up" as well as a week plan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('planned_meals', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('household_id')->constrained()->cascadeOnDelete();
            $table->foreignId('recipe_id')->constrained()->cascadeOnDelete();
            $table->foreignId('added_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('planned_for')->nullable();
            $table->timestamps();

            $table->index(['household_id', 'planned_for']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('planned_meals');
    }
};
