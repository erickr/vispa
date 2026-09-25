<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('household_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('household_id')->constrained()->cascadeOnDelete();
            $table->string('email');
            // Jetstream writes it; unused, as households have no roles.
            $table->string('role')->nullable();
            $table->timestamps();

            $table->unique(['household_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('household_invitations');
    }
};
