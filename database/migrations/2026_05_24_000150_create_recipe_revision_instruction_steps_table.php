<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recipe_revision_instruction_steps', function (Blueprint $table) {
            $table->id();

            $table->foreignId('recipe_revision_id')
                ->constrained('recipe_revisions', 'id', 'rriss_revision_fk')
                ->cascadeOnDelete();

            $table->foreignId('section_id')
                ->nullable()
                ->constrained('recipe_revision_instruction_sections', 'id', 'rriss_section_fk')
                ->nullOnDelete();

            $table->text('instruction_text');

            $table->integer('sort_order')->default(0);
            $table->integer('timer_seconds')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recipe_revision_instruction_steps');
    }
};
