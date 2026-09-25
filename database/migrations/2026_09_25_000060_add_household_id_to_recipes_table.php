<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A recipe belongs to a household, and everyone in it can open and edit it. Recipes written
 * before households existed go to their owner's own household.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recipes', function (Blueprint $table) {
            // Nullable, and nulled if the household is deleted: the recipe then falls back to
            // its owner rather than vanishing with the household.
            $table->foreignId('household_id')->nullable()->after('owner_user_id')->constrained()->nullOnDelete();
        });

        DB::table('recipes')->update([
            'household_id' => DB::raw('(select households.id from households where households.user_id = recipes.owner_user_id and households.personal_household = 1 order by households.id limit 1)'),
        ]);
    }

    public function down(): void
    {
        Schema::table('recipes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('household_id');
        });
    }
};
