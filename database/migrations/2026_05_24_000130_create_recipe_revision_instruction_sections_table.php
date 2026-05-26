<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recipe_revision_instruction_sections', function (Blueprint $table) {
            $table->id();

            $table->foreignId('recipe_revision_id')
                ->constrained('recipe_revisions', 'id', 'rris_revision_fk')
                ->cascadeOnDelete();

            $table->string('title')->nullable();

            $table->integer('sort_order')->default(0);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recipe_revision_instruction_sections');
    }
};
