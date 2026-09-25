<?php

namespace App\Actions\Families;

use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Gives a user the family they own and invite others into — Jetstream's "personal team".
 * Safe to call twice: a user who already has one keeps it.
 */
class CreatePersonalFamily
{
    public function handle(User $user): Team
    {
        return DB::transaction(function () use ($user): Team {
            $family = $user->personalTeam() ?? $user->ownedTeams()->forceCreate([
                'name' => Team::familyNameFor((string) $user->name, $user->preferredLocale()),
                'personal_team' => true,
            ]);

            if ($user->current_team_id === null) {
                $user->forceFill(['current_team_id' => $family->getKey()])->save();
            }

            return $family;
        });
    }
}
