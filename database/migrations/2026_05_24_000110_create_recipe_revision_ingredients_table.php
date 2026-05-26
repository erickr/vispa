<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recipe_revision_ingredients', function (Blueprint $table) {
            $table->id();

            $table->foreignId('recipe_revision_id')
                ->constrained('recipe_revisions')
                ->cascadeOnDelete();

            $table->foreignId('group_id')
                ->nullable()
                ->constrained('recipe_revision_ingredient_groups', 'id', 'rri_group_fk')
                ->nullOnDelete();

            $table->foreignId('ingredient_id')
                ->constrained('ingredients');

            $table->decimal('quantity', 10, 3)->nullable();
            $table->decimal('quantity_min', 10, 3)->nullable();
            $table->decimal('quantity_max', 10, 3)->nullable();

            $table->foreignId('unit_id')
                ->nullable()
                ->constrained('units')
                ->nullOnDelete();

            $table->boolean('optional')->default(false);

            $table->string('preparation_note')->nullable();

            $table->integer('sort_order')->default(0);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recipe_revision_ingredients');
    }
};
