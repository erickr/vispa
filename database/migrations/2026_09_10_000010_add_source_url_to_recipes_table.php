<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Where a recipe came from. This sits on the recipe identity rather than on a revision: the
     * link is the same whatever locale or version you are reading, and keeping it here lets a
     * saved link be de-duplicated across the whole cookbook. The prose credit that goes with it
     * ("Mormor Ingrid's notebook") is language-specific and lives on the revision instead.
     */
    public function up(): void
    {
        Schema::table('recipes', function (Blueprint $table) {
            // 500 chars keeps the index under InnoDB's 3072-byte key limit on utf8mb4.
            $table->string('source_url', 500)->nullable()->after('visibility');

            $table->index('source_url');
        });
    }

    public function down(): void
    {
        Schema::table('recipes', function (Blueprint $table) {
            $table->dropIndex(['source_url']);
            $table->dropColumn('source_url');
        });
    }
};
