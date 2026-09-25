<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Laravel\Jetstream\TeamInvitation as JetstreamTeamInvitation;

class HouseholdInvitation extends JetstreamTeamInvitation
{
    protected $fillable = [
        'email',
        'role',
    ];

    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class);
    }

    /**
     * Jetstream's name for the same relation; its own version would look for `team_id`.
     */
    public function team(): BelongsTo
    {
        return $this->household();
    }
}
