<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Recipe extends Model
{
    use HasUuid;

    protected $fillable = [
        'uuid',
        'owner_user_id',
        'household_id',
        'forked_from_recipe_id',
        'forked_from_revision_id',
        'default_locale',
        'visibility',
        'source_url',
    ];

    /**
     * Memo for sharedRevisions(): the public page asks for the locales and then for a revision.
     *
     * @var Collection<int, RecipeRevision>|null
     */
    private ?Collection $sharedRevisions = null;

    protected static function booted(): void
    {
        // A new recipe lands in the household its owner is currently in, however it was made —
        // the create form, an import, a fork.
        static::creating(function (Recipe $recipe): void {
            if ($recipe->household_id === null && $recipe->owner_user_id !== null) {
                $recipe->household_id = User::find($recipe->owner_user_id)?->currentTeam?->getKey();
            }
        });
    }

    /**
     * What a user can open and edit: every recipe in any household they belong to, plus their
     * own wherever they are — so leaving a household does not take away what you wrote.
     */
    public function scopeAccessibleTo(Builder $query, ?User $user): void
    {
        if (! $user) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->where(fn (Builder $query) => $query
            ->whereIn($query->qualifyColumn('household_id'), $user->allTeams()->modelKeys())
            ->orWhere($query->qualifyColumn('owner_user_id'), $user->getKey()));
    }

    /**
     * The household this recipe belongs to.
     */
    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class);
    }

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

    /**
     * The bare host of the source link, for showing "ica.se" rather than the whole URL.
     */
    public function sourceHost(): ?string
    {
        $host = $this->source_url ? parse_url($this->source_url, PHP_URL_HOST) : null;

        return $host ? preg_replace('/^www\./', '', $host) : null;
    }

    /**
     * A private recipe has no public page at all; unlisted and public both do, the difference
     * being only that nothing links to an unlisted one.
     */
    public function isShareable(): bool
    {
        return $this->visibility !== 'private';
    }

    public function shareUrl(): string
    {
        return route('recipes.share', ['recipe' => $this->uuid]);
    }

    /**
     * What a shared link may show: the published revisions, newest version first. While nothing
     * is published yet, the display revision stands in, so a link handed out early still opens
     * rather than 404ing — it is the owner's own link to give.
     *
     * @return Collection<int, RecipeRevision>
     */
    public function sharedRevisions(): Collection
    {
        return $this->sharedRevisions ??= (function (): Collection {
            $published = $this->revisions()
                ->where('status', 'published')
                ->orderByDesc('version_number')
                ->get();

            if ($published->isNotEmpty()) {
                return $published;
            }

            $display = $this->displayRevision();

            return new Collection($display ? [$display] : []);
        })();
    }

    /**
     * @return array<int, string>
     */
    public function sharedLocales(): array
    {
        return $this->sharedRevisions()->pluck('locale')->unique()->values()->all();
    }

    /**
     * The revision a visitor reading in $locale gets: that language if it is shared, else the
     * recipe's own default locale, else whatever there is.
     */
    public function sharedRevision(?string $locale = null): ?RecipeRevision
    {
        $revisions = $this->sharedRevisions();

        return $revisions->firstWhere('locale', $locale)
            ?? $revisions->firstWhere('locale', $this->default_locale)
            ?? $revisions->first();
    }

    /**
     * The revision to surface when the owner looks at their own recipe: their newest work in the
     * recipe's default locale. A draft started off a published version therefore takes over the
     * listing — it is what they are cooking from now — while the published version keeps the
     * public page to itself (see sharedRevisions()). An archived revision only shows when there
     * is nothing else left.
     */
    public function displayRevision(): ?RecipeRevision
    {
        // Sorted in PHP when the caller has already eager-loaded revisions — the recipes table
        // renders a title, a status and a photo per row, and a query each would be N+1 three
        // times over. The sort key mirrors the SQL ordering below exactly.
        if ($this->relationLoaded('revisions')) {
            return $this->revisions
                ->sortByDesc(fn (RecipeRevision $revision): string => sprintf(
                    '%d%d%09d',
                    $revision->status === 'archived' ? 0 : 1,
                    $revision->locale === $this->default_locale ? 1 : 0,
                    $revision->version_number,
                ))
                ->first();
        }

        return $this->revisions()
            ->orderByRaw("(status = 'archived') asc")
            ->orderByRaw('(locale = ?) desc', [$this->default_locale])
            ->orderByDesc('version_number')
            ->first();
    }
}
