<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recipe_revisions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->foreignId('recipe_id')->constrained('recipes')->cascadeOnDelete();

            $table->string('locale', 10);

            $table->integer('version_number');

            $table->enum('status', ['draft', 'published', 'archived'])->default('draft');

            $table->string('title');
            $table->text('description')->nullable();
            $table->text('notes')->nullable();

            $table->integer('servings')->nullable();
            $table->integer('prep_time_minutes')->nullable();
            $table->integer('cook_time_minutes')->nullable();

            $table->foreignId('created_by_user_id')->constrained('users');

            $table->timestamp('created_at')->nullable();
            $table->timestamp('published_at')->nullable();

            $table->unique(['recipe_id', 'locale', 'version_number']);
        });

        Schema::table('recipes', function (Blueprint $table) {
            $table->foreign('forked_from_revision_id')
                ->references('id')->on('recipe_revisions')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('recipes', function (Blueprint $table) {
            $table->dropForeign(['forked_from_revision_id']);
        });
        Schema::dropIfExists('recipe_revisions');
    }
};
