<?php

namespace App\Actions\Jetstream;

use App\Models\Household;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Laravel\Jetstream\Contracts\CreatesTeams;
use Laravel\Jetstream\Events\AddingTeam;
use Laravel\Jetstream\Jetstream;

class CreateTeam implements CreatesTeams
{
    /**
     * Validate and create a new household for the given user.
     *
     * @param  array<string, string>  $input
     */
    public function create(User $user, array $input): Household
    {
        Gate::forUser($user)->authorize('create', Jetstream::newTeamModel());

        Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
        ])->validateWithBag('createTeam');

        AddingTeam::dispatch($user);

        $user->switchTeam($household = $user->ownedTeams()->create([
            'name' => $input['name'],
            'personal_household' => false,
        ]));

        return $household;
    }
}
