<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecipeRevisionImage extends Model
{
    use HasUuid;

    public const UPDATED_AT = null;

    protected $fillable = [
        'uuid',
        'recipe_revision_id',
        'path',
        'alt_text',
        'is_cover',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_cover' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // At most one cover per revision. The query-builder update deliberately bypasses model
        // events, so demoting the siblings cannot re-enter this hook.
        static::saved(function (self $image): void {
            if (! $image->is_cover) {
                return;
            }

            static::query()
                ->where('recipe_revision_id', $image->recipe_revision_id)
                ->whereKeyNot($image->getKey())
                ->update(['is_cover' => false]);
        });
    }

    public function revision(): BelongsTo
    {
        return $this->belongsTo(RecipeRevision::class, 'recipe_revision_id');
    }
}
