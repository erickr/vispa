<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A household is Jetstream's team under our own name (see App\Models\Household).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('households', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            // The owner.
            $table->foreignId('user_id')->index();
            $table->string('name');
            // The one household every user owns and cannot delete or leave.
            $table->boolean('personal_household');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('households');
    }
};
