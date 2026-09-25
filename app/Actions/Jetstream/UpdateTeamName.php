<?php

namespace App\Actions\Jetstream;

use App\Models\Household;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Laravel\Jetstream\Contracts\UpdatesTeamNames;

class UpdateTeamName implements UpdatesTeamNames
{
    /**
     * Validate and update the given household's name.
     *
     * @param  array<string, string>  $input
     */
    public function update(User $user, Household $household, array $input): void
    {
        Gate::forUser($user)->authorize('update', $household);

        Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
        ])->validateWithBag('updateTeamName');

        $household->forceFill([
            'name' => $input['name'],
        ])->save();
    }
}
