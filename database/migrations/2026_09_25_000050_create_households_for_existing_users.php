<?php

use App\Actions\Households\CreatePersonalHousehold;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;

/**
 * Everyone who signed up before households existed gets one of their own, named after their
 * last name, so they have somewhere to invite the rest of the household. New accounts get
 * theirs on registration (see AppServiceProvider).
 */
return new class extends Migration
{
    public function up(): void
    {
        User::query()->lazyById()->each(
            fn (User $user) => app(CreatePersonalHousehold::class)->handle($user)
        );
    }

    public function down(): void
    {
        // The households tables are dropped by their own migrations.
    }
};
