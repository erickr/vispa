<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Database\Factories\HouseholdFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;
use Laravel\Jetstream\Events\TeamCreated;
use Laravel\Jetstream\Events\TeamDeleted;
use Laravel\Jetstream\Events\TeamUpdated;
use Laravel\Jetstream\Team as JetstreamTeam;

/**
 * A household: Jetstream's team under our own name, registered with Jetstream::useTeamModel().
 * Each user owns one (their personal household, created on sign-up) and can invite the people
 * they cook with into it.
 *
 * Jetstream derives most keys from this class name (household_id on the pivot, the
 * invitations and recipes). The two methods below replace Jetstream's own, which hardcode
 * `current_household_id`.
 */
class Household extends JetstreamTeam
{
    /** @use HasFactory<HouseholdFactory> */
    use HasFactory, HasUuid;

    protected $fillable = [
        'name',
        'personal_household',
    ];

    protected $dispatchesEvents = [
        'created' => TeamCreated::class,
        'updated' => TeamUpdated::class,
        'deleted' => TeamDeleted::class,
    ];

    protected function casts(): array
    {
        return [
            'personal_household' => 'boolean',
        ];
    }

    /**
     * @param  User  $user
     */
    public function removeUser($user): void
    {
        if ($user->current_household_id === $this->id) {
            $user->forceFill(['current_household_id' => null])->save();
        }

        $this->users()->detach($user);
    }

    public function purge(): void
    {
        $this->owner()->where('current_household_id', $this->id)->update(['current_household_id' => null]);
        $this->users()->where('current_household_id', $this->id)->update(['current_household_id' => null]);
        $this->users()->detach();

        $this->delete();
    }

    /**
     * Other households' recipes kept here to read and cook from (see Recipe::scopeSavedBy()).
     */
    public function savedRecipes(): BelongsToMany
    {
        return $this->belongsToMany(Recipe::class, 'household_saved_recipes')
            ->withPivot('saved_by_user_id')
            ->withTimestamps();
    }

    /**
     * "The Krona household" for Eric Krona: the last word of the name is taken as the surname,
     * which is right more often than not and is only a starting point — the owner can rename it.
     */
    public static function defaultNameFor(string $name, ?string $locale = null): string
    {
        $surname = Str::of($name)->squish()->explode(' ')->last();

        return __('household.default_name', ['surname' => $surname !== '' ? $surname : $name], $locale);
    }
}
