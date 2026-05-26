<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IngredientTranslation extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'ingredient_id',
        'locale',
        'name',
    ];

    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class);
    }
}
