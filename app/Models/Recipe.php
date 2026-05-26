<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Recipe extends Model
{
    use HasUuid;

    protected $fillable = [
        'uuid',
        'owner_user_id',
        'forked_from_recipe_id',
        'forked_from_revision_id',
        'default_locale',
        'visibility',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function forkedFromRecipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class, 'forked_from_recipe_id');
    }

    public function forkedFromRevision(): BelongsTo
    {
        return $this->belongsTo(RecipeRevision::class, 'forked_from_revision_id');
    }

    public function forks(): HasMany
    {
        return $this->hasMany(Recipe::class, 'forked_from_recipe_id');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(RecipeRevision::class);
    }

    public function localeSlugs(): HasMany
    {
        return $this->hasMany(RecipeLocaleSlug::class);
    }
}
