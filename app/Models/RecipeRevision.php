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

        return $draft;
    }
}
