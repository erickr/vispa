<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RecipeRevision extends Model
{
    use HasUuid;

    public const UPDATED_AT = null;

    protected $fillable = [
        'uuid',
        'recipe_id',
        'locale',
        'version_number',
        'status',
        'title',
        'description',
        'notes',
        'servings',
        'prep_time_minutes',
        'cook_time_minutes',
        'created_by_user_id',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
        ];
    }

    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function ingredientGroups(): HasMany
    {
        return $this->hasMany(RecipeRevisionIngredientGroup::class)->orderBy('sort_order');
    }

    public function ingredients(): HasMany
    {
        return $this->hasMany(RecipeRevisionIngredient::class)->orderBy('sort_order');
    }

    public function instructionSections(): HasMany
    {
        return $this->hasMany(RecipeRevisionInstructionSection::class)->orderBy('sort_order');
    }

    public function instructionSteps(): HasMany
    {
        return $this->hasMany(RecipeRevisionInstructionStep::class)->orderBy('sort_order');
    }
}
