<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RecipeRevisionInstructionSection extends Model
{
    public $timestamps = false;

    protected $table = 'recipe_revision_instruction_sections';

    protected $fillable = [
        'recipe_revision_id',
        'title',
        'sort_order',
    ];

    public function revision(): BelongsTo
    {
        return $this->belongsTo(RecipeRevision::class, 'recipe_revision_id');
    }

    public function steps(): HasMany
    {
        return $this->hasMany(RecipeRevisionInstructionStep::class, 'section_id')->orderBy('sort_order');
    }
}
