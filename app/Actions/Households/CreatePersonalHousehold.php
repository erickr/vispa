<?php

namespace App\Actions\Households;

use App\Models\Household;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Gives a user the household they own and invite others into — Jetstream's "personal team".
 * Safe to call twice: a user who already has one keeps it.
 */
class CreatePersonalHousehold
{
    public function handle(User $user): Household
    {
        return DB::transaction(function () use ($user): Household {
            $household = $user->personalTeam() ?? $user->ownedTeams()->forceCreate([
                'name' => Household::defaultNameFor((string) $user->name, $user->preferredLocale()),
                'personal_household' => true,
            ]);

            if ($user->current_household_id === null) {
                $user->forceFill(['current_household_id' => $household->getKey()])->save();
            }

            return $household;
        });
    }
}
