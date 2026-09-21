<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;

/**
 * A catalog ingredient is either shared (no owner, curated by the catalog admin) or private
 * to the user who created it. Visibility is a local scope, not a global one, so a recipe that
 * already points at someone's private ingredient still resolves its name.
 */
class Ingredient extends Model
{
    use HasUuid;

    protected $fillable = [
        'uuid',
        'canonical_name',
    ];

    protected function casts(): array
    {
        return [
            'owner_user_id' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        // Whoever creates an ingredient owns it, except the catalog admin, whose additions are
        // shared. Covers both the Ingredients page and the recipe editor's inline create form.
        static::creating(function (Ingredient $ingredient): void {
            $user = Auth::user();

            if ($ingredient->owner_user_id === null && $user instanceof User && ! $user->isCatalogAdmin()) {
                $ingredient->owner_user_id = $user->getKey();
            }
        });
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function translations(): HasMany
    {
        return $this->hasMany(IngredientTranslation::class);
    }

    public function isShared(): bool
    {
        return $this->owner_user_id === null;
    }

    /**
     * Shared ingredients plus the user's own. `$alsoInclude` keeps an already-picked ingredient
     * resolvable even when it isn't visible to this user.
     */
    public function scopeVisibleTo(Builder $query, ?User $user, int|string|null $alsoInclude = null): void
    {
        $query->where(function (Builder $query) use ($user, $alsoInclude): void {
            $query->whereNull('owner_user_id');

            if ($user) {
                $query->orWhere('owner_user_id', $user->getKey());
            }

            if (filled($alsoInclude)) {
                $query->orWhere($query->qualifyColumn('id'), $alsoInclude);
            }
        });
    }

    public function nameFor(string $locale, ?string $fallback = null): string
    {
        $translation = $this->translations
            ->firstWhere('locale', $locale)
            ?? ($fallback ? $this->translations->firstWhere('locale', $fallback) : null);

        return $translation?->name ?? $this->canonical_name;
    }
}
