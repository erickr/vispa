<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecipeRevisionIngredient extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'recipe_revision_id',
        'group_id',
        'ingredient_id',
        'quantity',
        'quantity_min',
        'quantity_max',
        'unit_id',
        'optional',
        'preparation_note',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'quantity_min' => 'decimal:3',
            'quantity_max' => 'decimal:3',
            'optional' => 'boolean',
        ];
    }

    public function revision(): BelongsTo
    {
        return $this->belongsTo(RecipeRevision::class, 'recipe_revision_id');
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(RecipeRevisionIngredientGroup::class, 'group_id');
    }

    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }
}
