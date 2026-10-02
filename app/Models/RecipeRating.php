<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A household's 1–5 verdict on a dish it cooked, given when the meal comes off the plan (see
 * App\Actions\Meals\RemovePlannedMeal). The household's, like the plan, but it remembers who gave it.
 */
class RecipeRating extends Model
{
    use HasUuid;

    public const MIN = 1;

    public const MAX = 5;

    protected $fillable = [
        'uuid',
        'recipe_id',
        'recipe_revision_id',
        'household_id',
        'rated_by_user_id',
        'rating',
    ];

    protected function casts(): array
    {
        return [
            'rating' => 'integer',
        ];
    }

    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class);
    }

    public function revision(): BelongsTo
    {
        return $this->belongsTo(RecipeRevision::class, 'recipe_revision_id');
    }

    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class);
    }

    public function ratedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rated_by_user_id');
    }
}
