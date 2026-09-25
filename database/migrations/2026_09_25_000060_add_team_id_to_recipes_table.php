<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A recipe belongs to a family, and everyone in it can open and edit it. Recipes written
 * before families existed go to their owner's own family.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recipes', function (Blueprint $table) {
            // Nullable, and nulled if the family is deleted: the recipe then falls back to its
            // owner rather than vanishing with the family.
            $table->foreignId('team_id')->nullable()->after('owner_user_id')->constrained()->nullOnDelete();
        });

        DB::table('recipes')->update([
            'team_id' => DB::raw('(select teams.id from teams where teams.user_id = recipes.owner_user_id and teams.personal_team = 1 order by teams.id limit 1)'),
        ]);
    }

    public function down(): void
    {
        Schema::table('recipes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('team_id');
        });
    }
};
