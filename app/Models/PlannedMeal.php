<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A dish a household plans to cook. It belongs to the household, not the person who added it,
 * so everyone in it shares one list. The recipe may be the household's own or one it saved
 * (see App\Actions\Meals\PlanMeal).
 */
class PlannedMeal extends Model
{
    use HasUuid;

    protected $fillable = [
        'uuid',
        'household_id',
        'recipe_id',
        'added_by_user_id',
        'planned_for',
    ];

    protected function casts(): array
    {
        return [
            'planned_for' => 'date',
        ];
    }

    /**
     * The plan the user is looking at: their current household's.
     */
    public function scopeForCurrentHouseholdOf(Builder $query, ?User $user): void
    {
        $household = $user?->currentTeam;

        if (! $household) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->where($query->qualifyColumn('household_id'), $household->getKey());
    }

    /**
     * Whether the user is in the household the meal is planned for.
     */
    public function isManageableBy(?User $user): bool
    {
        return $user !== null
            && in_array((int) $this->household_id, array_map('intval', $user->allTeams()->modelKeys()), true);
    }

    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class);
    }

    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class);
    }

    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by_user_id');
    }
}
