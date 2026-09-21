<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per import attempt: what was read, how it went, and what it cost. source_type and
     * source_path leave room for PDFs, photos and pasted text next to links.
     */
    public function up(): void
    {
        Schema::create('recipe_imports', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('source_type', 20)->default('url');
            $table->string('source_url', 500)->nullable();
            $table->string('source_path')->nullable();
            $table->string('status', 20)->default('pending');
            $table->text('error')->nullable();
            $table->foreignId('recipe_id')->nullable()->constrained('recipes')->nullOnDelete();
            $table->foreignId('recipe_revision_id')->nullable()->constrained('recipe_revisions')->nullOnDelete();
            $table->string('model')->nullable();
            $table->unsignedInteger('input_tokens')->nullable();
            $table->unsignedInteger('output_tokens')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recipe_imports');
    }
};
