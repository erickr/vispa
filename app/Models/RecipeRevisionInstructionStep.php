<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecipeRevisionInstructionStep extends Model
{
    public $timestamps = false;

    protected $table = 'recipe_revision_instruction_steps';

    protected $fillable = [
        'recipe_revision_id',
        'section_id',
        'instruction_text',
        'sort_order',
        'timer_seconds',
    ];

    public function revision(): BelongsTo
    {
        return $this->belongsTo(RecipeRevision::class, 'recipe_revision_id');
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(RecipeRevisionInstructionSection::class, 'section_id');
    }
}
