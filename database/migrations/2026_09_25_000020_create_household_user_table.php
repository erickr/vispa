<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Household members other than the owner (App\Models\HouseholdMembership).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('household_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('household_id');
            $table->foreignId('user_id');
            // Jetstream writes it; unused, as households have no roles.
            $table->string('role')->nullable();
            $table->timestamps();

            $table->unique(['household_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('household_user');
    }
};
