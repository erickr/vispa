<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Photos hang off the revision like the rest of the structured content: alt_text is prose in
     * the revision's locale, and a draft fork gets its own rows pointing at the same stored files.
     */
    public function up(): void
    {
        Schema::create('recipe_revision_images', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->foreignId('recipe_revision_id')
                ->constrained('recipe_revisions', 'id', 'rrimg_revision_fk')
                ->cascadeOnDelete();

            $table->string('path');
            $table->string('alt_text')->nullable();

            $table->boolean('is_cover')->default(false);
            $table->integer('sort_order')->default(0);

            $table->timestamp('created_at')->nullable();

            $table->index(['recipe_revision_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recipe_revision_images');
    }
};
