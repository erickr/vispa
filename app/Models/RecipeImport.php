<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One attempt at importing a recipe from somewhere else. The job fills in the outcome; the
 * status page polls it.
 */
class RecipeImport extends Model
{
    use HasUuid;

    public const STATUS_PENDING = 'pending';

    public const STATUS_RUNNING = 'running';

    public const STATUS_DONE = 'done';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'uuid',
        'user_id',
        'source_type',
        'source_url',
        'source_path',
        'status',
        'error',
        'recipe_id',
        'recipe_revision_id',
        'model',
        'input_tokens',
        'output_tokens',
    ];

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function isFinished(): bool
    {
        return in_array($this->status, [self::STATUS_DONE, self::STATUS_FAILED], true);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class);
    }

    public function revision(): BelongsTo
    {
        return $this->belongsTo(RecipeRevision::class, 'recipe_revision_id');
    }
}
