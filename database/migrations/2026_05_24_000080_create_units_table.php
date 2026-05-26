<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('units', function (Blueprint $table) {
            $table->id();

            $table->string('code', 50)->unique();

            $table->enum('type', ['mass', 'volume', 'count']);

            $table->unsignedBigInteger('base_unit_id')->nullable();

            $table->decimal('factor_to_base', 12, 6)->default(1);

            $table->foreign('base_unit_id')
                ->references('id')->on('units')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('units', function (Blueprint $table) {
            $table->dropForeign(['base_unit_id']);
        });
        Schema::dropIfExists('units');
    }
};
