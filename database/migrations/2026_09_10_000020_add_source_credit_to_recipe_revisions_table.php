<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Who to thank, in the revision's own locale — "Mormor Ingrid's notebook", "Leif Mannerström".
     * Revision-scoped like every other piece of prose, so a Swedish and an English revision can
     * word the credit differently.
     */
    public function up(): void
    {
        Schema::table('recipe_revisions', function (Blueprint $table) {
            $table->string('source_credit')->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('recipe_revisions', function (Blueprint $table) {
            $table->dropColumn('source_credit');
        });
    }
};
