<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RecipeRevisionIngredientGroup extends Model
{
    public $timestamps = false;

    protected $table = 'recipe_revision_ingredient_groups';

    protected $fillable = [
        'recipe_revision_id',
        'title',
        'sort_order',
    ];

    public function revision(): BelongsTo
    {
        return $this->belongsTo(RecipeRevision::class, 'recipe_revision_id');
    }

    public function ingredients(): HasMany
    {
        return $this->hasMany(RecipeRevisionIngredient::class, 'group_id')->orderBy('sort_order');
    }
}
