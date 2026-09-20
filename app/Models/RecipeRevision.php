<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RecipeRevision extends Model
{
    use HasUuid;

    public const UPDATED_AT = null;

    protected $fillable = [
        'uuid',
        'recipe_id',
        'locale',
        'version_number',
        'status',
        'title',
        'description',
        'notes',
        'source_credit',
        'servings',
        'prep_time_minutes',
        'cook_time_minutes',
        'created_by_user_id',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
        ];
    }

    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function ingredientGroups(): HasMany
    {
        return $this->hasMany(RecipeRevisionIngredientGroup::class)->orderBy('sort_order');
    }

    public function ingredients(): HasMany
    {
        return $this->hasMany(RecipeRevisionIngredient::class)->orderBy('sort_order');
    }

    public function instructionSections(): HasMany
    {
        return $this->hasMany(RecipeRevisionInstructionSection::class)->orderBy('sort_order');
    }

    public function instructionSteps(): HasMany
    {
        return $this->hasMany(RecipeRevisionInstructionStep::class)->orderBy('sort_order');
    }

    public function images(): HasMany
    {
        return $this->hasMany(RecipeRevisionImage::class)->orderBy('sort_order');
    }

    /**
     * The photo to lead with: the one flagged as cover, else the first in sort order.
     */
    public function coverImage(): ?RecipeRevisionImage
    {
        // reorder() drops the relation's own sort_order clause, so the flag wins and sort order
        // only breaks the tie.
        return $this->images()->reorder()->orderByDesc('is_cover')->orderBy('sort_order')->first();
    }

    /**
     * A recipe that is nothing but a saved link — no ingredients, no steps. Perfectly valid:
     * the link is the recipe until someone feels like writing it out.
     */
    public function isLinkOnly(): bool
    {
        return ! $this->ingredients()->exists() && ! $this->instructionSteps()->exists();
    }

    /**
     * How a status reads to the cook — "Ready" rather than "published". A status with no
     * wording of its own is shown raw rather than as a missing translation key.
     */
    public static function statusWord(?string $status): ?string
    {
        if ($status === null) {
            return null;
        }

        $key = 'revision.status_words.'.$status;

        return __($key) === $key ? $status : __($key);
    }

    /**
     * Append-only invariant: a published revision is never mutated. Editing one instead works on a
     * draft fork — a new revision in the same locale (next version number) with all content
     * deep-copied. The group/section parent links are remapped to the clone's own rows.
     */
    public function draftFork(?int $createdByUserId = null): self
    {
        $nextVersion = (int) static::query()
            ->where('recipe_id', $this->recipe_id)
            ->where('locale', $this->locale)
            ->max('version_number') + 1;

        $draft = static::create([
            'recipe_id' => $this->recipe_id,
            'locale' => $this->locale,
            'version_number' => $nextVersion,
            'status' => 'draft',
            'title' => $this->title,
            'description' => $this->description,
            'notes' => $this->notes,
            'source_credit' => $this->source_credit,
            'servings' => $this->servings,
            'prep_time_minutes' => $this->prep_time_minutes,
            'cook_time_minutes' => $this->cook_time_minutes,
            'created_by_user_id' => $createdByUserId,
            'published_at' => null,
        ]);

        $groupMap = [];
        foreach ($this->ingredientGroups()->get() as $group) {
            $clone = $draft->ingredientGroups()->create([
                'title' => $group->title,
                'sort_order' => $group->sort_order,
            ]);
            $groupMap[$group->id] = $clone->id;
        }

        foreach ($this->ingredients()->get() as $ingredient) {
            $draft->ingredients()->create([
                'group_id' => $ingredient->group_id ? ($groupMap[$ingredient->group_id] ?? null) : null,
                'ingredient_id' => $ingredient->ingredient_id,
                'quantity' => $ingredient->quantity,
                'quantity_min' => $ingredient->quantity_min,
                'quantity_max' => $ingredient->quantity_max,
                'unit_id' => $ingredient->unit_id,
                'optional' => $ingredient->optional,
                'preparation_note' => $ingredient->preparation_note,
                'sort_order' => $ingredient->sort_order,
            ]);
        }

        $sectionMap = [];
        foreach ($this->instructionSections()->get() as $section) {
            $clone = $draft->instructionSections()->create([
                'title' => $section->title,
                'sort_order' => $section->sort_order,
            ]);
            $sectionMap[$section->id] = $clone->id;
        }

        foreach ($this->instructionSteps()->get() as $step) {
            $draft->instructionSteps()->create([
                'section_id' => $step->section_id ? ($sectionMap[$step->section_id] ?? null) : null,
                'instruction_text' => $step->instruction_text,
                'sort_order' => $step->sort_order,
                'timer_seconds' => $step->timer_seconds,
            ]);
        }

        // Photo rows are cloned; the stored files are shared between revisions, so deleting a
        // revision never removes a file another revision still points at.
        foreach ($this->images()->get() as $image) {
            $draft->images()->create([
                'path' => $image->path,
                'alt_text' => $image->alt_text,
                'is_cover' => $image->is_cover,
                'sort_order' => $image->sort_order,
            ]);
        }

        return $draft;
    }
}
