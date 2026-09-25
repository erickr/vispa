<?php

namespace App\Actions\Jetstream;

use App\Models\Household;
use Laravel\Jetstream\Contracts\DeletesTeams;

class DeleteTeam implements DeletesTeams
{
    /**
     * Delete the given household.
     */
    public function delete(Household $household): void
    {
        $household->purge();
    }
}
