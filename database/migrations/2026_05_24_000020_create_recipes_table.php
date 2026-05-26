<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recipes', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->foreignId('owner_user_id')->constrained('users');

            $table->unsignedBigInteger('forked_from_recipe_id')->nullable();
            $table->unsignedBigInteger('forked_from_revision_id')->nullable();

            $table->string('default_locale', 10)->default('en');

            $table->enum('visibility', ['private', 'unlisted', 'public'])->default('private');

            $table->timestamps();

            $table->index('forked_from_recipe_id');
            $table->index('forked_from_revision_id');
        });

        Schema::table('recipes', function (Blueprint $table) {
            $table->foreign('forked_from_recipe_id')
                ->references('id')->on('recipes')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('recipes', function (Blueprint $table) {
            $table->dropForeign(['forked_from_recipe_id']);
        });
        Schema::dropIfExists('recipes');
    }
};
