<?php

namespace App\Recipes\Import;

use App\Models\Ingredient;
use App\Models\Recipe;
use App\Models\RecipeRevision;
use App\Models\Unit;
use App\Models\User;
use App\Support\SafeUrlFetcher;
use App\Support\SupportedLocales;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Writes an extracted recipe as a new private recipe with one draft revision, owned by the
 * importing user. Ingredients are matched against what that user can see; anything new becomes
 * their own private ingredient.
 */
class RecipeImporter
{
    /** Unit spellings the extractor may still return that aren't catalog codes. */
    private const UNIT_ALIASES = [
        'l' => 'liter',
        'st.' => 'st',
        'stycken' => 'st',
    ];

    public function __construct(private readonly SafeUrlFetcher $fetcher = new SafeUrlFetcher) {}

    public function import(ExtractedRecipe $extracted, User $user, ?string $sourceUrl = null): RecipeRevision
    {
        $locale = SupportedLocales::isSupported($extracted->locale)
            ? $extracted->locale
            : SupportedLocales::sanitize($user->locale);

        $revision = DB::transaction(function () use ($extracted, $user, $sourceUrl, $locale): RecipeRevision {
            $recipe = Recipe::create([
                'owner_user_id' => $user->getKey(),
                'default_locale' => $locale,
                'visibility' => 'private',
                'source_url' => $sourceUrl,
            ]);

            $revision = $recipe->revisions()->create([
                'locale' => $locale,
                'version_number' => 1,
                'status' => 'draft',
                'title' => $extracted->title ?? __('recipe.untitled', locale: $locale),
                'description' => $extracted->description,
                'notes' => $this->notes($extracted, $locale),
                'source_credit' => $extracted->sourceCredit,
                'servings' => $extracted->servings,
                'prep_time_minutes' => $extracted->prepMinutes,
                'cook_time_minutes' => $extracted->cookMinutes,
                'created_by_user_id' => $user->getKey(),
            ]);

            $this->writeIngredients($revision, $extracted, $user, $locale);
            $this->writeSteps($revision, $extracted);

            return $revision;
        });

        // Outside the transaction: a slow or failing photo must not hold or undo the recipe.
        if ($extracted->imageUrl) {
            $this->attachCover($revision, $extracted->imageUrl);
        }

        return $revision;
    }

    private function writeIngredients(RecipeRevision $revision, ExtractedRecipe $extracted, User $user, string $locale): void
    {
        $units = Unit::query()->pluck('id', 'code')->mapWithKeys(fn ($id, $code) => [mb_strtolower($code) => $id]);
        $resolved = [];
        $sort = 0;

        foreach ($extracted->ingredientGroups as $groupIndex => $group) {
            $groupId = null;

            // A lone untitled group is just "the ingredients"; don't wrap it in a group row.
            if ($group['title'] !== null || count($extracted->ingredientGroups) > 1) {
                $groupId = $revision->ingredientGroups()->create([
                    'title' => $group['title'],
                    'sort_order' => $groupIndex + 1,
                ])->getKey();
            }

            foreach ($group['items'] as $item) {
                [$unitId, $unitNote] = $this->unit($item['unit'], $units->all());

                $key = mb_strtolower($item['ingredient']);
                $resolved[$key] ??= $this->ingredient($item['ingredient'], $user, $locale)->getKey();

                $revision->ingredients()->create([
                    'recipe_revision_id' => $revision->getKey(),
                    'group_id' => $groupId,
                    'ingredient_id' => $resolved[$key],
                    'quantity' => $item['quantity'],
                    'quantity_min' => $item['quantity_min'],
                    'quantity_max' => $item['quantity_max'],
                    'unit_id' => $unitId,
                    'optional' => $item['optional'],
                    'preparation_note' => $this->join([$unitNote, $item['preparation_note']]),
                    'sort_order' => ++$sort,
                ]);
            }
        }
    }

    private function writeSteps(RecipeRevision $revision, ExtractedRecipe $extracted): void
    {
        $sort = 0;

        foreach ($extracted->instructionSections as $sectionIndex => $section) {
            $sectionId = null;

            if ($section['title'] !== null || count($extracted->instructionSections) > 1) {
                $sectionId = $revision->instructionSections()->create([
                    'title' => $section['title'],
                    'sort_order' => $sectionIndex + 1,
                ])->getKey();
            }

            foreach ($section['steps'] as $step) {
                $revision->instructionSteps()->create([
                    'recipe_revision_id' => $revision->getKey(),
                    'section_id' => $sectionId,
                    'instruction_text' => $step['text'],
                    'timer_seconds' => $step['timer_seconds'],
                    'sort_order' => ++$sort,
                ]);
            }
        }
    }

    /**
     * An ingredient the user can see — by canonical name or by its name in the revision's
     * locale — or a new private one.
     */
    private function ingredient(string $name, User $user, string $locale): Ingredient
    {
        $lower = mb_strtolower($name);

        return Ingredient::query()
            ->visibleTo($user)
            ->where(fn ($query) => $query
                ->whereRaw('LOWER(canonical_name) = ?', [$lower])
                ->orWhereHas('translations', fn ($query) => $query
                    ->where('locale', $locale)
                    ->whereRaw('LOWER(name) = ?', [$lower])))
            // Prefer the shared catalog row over a private duplicate.
            ->orderByRaw('owner_user_id IS NOT NULL')
            ->first()
            ?? $this->newIngredient($name, $user);
    }

    /**
     * Owned the way the Ingredient creating hook would own it, set explicitly because a queued
     * job has no signed-in user: private to the importer, shared when the catalog admin imports.
     */
    private function newIngredient(string $name, User $user): Ingredient
    {
        $ingredient = new Ingredient(['canonical_name' => $name]);
        $ingredient->owner_user_id = $user->isCatalogAdmin() ? null : $user->getKey();
        $ingredient->save();

        return $ingredient;
    }

    /**
     * @param  array<string, int>  $units  lowercase code => id
     * @return array{0: ?int, 1: ?string} the unit id, or the unit word to keep in the note
     */
    private function unit(?string $unit, array $units): array
    {
        if ($unit === null) {
            return [null, null];
        }

        $code = mb_strtolower($unit);
        $code = self::UNIT_ALIASES[$code] ?? $code;

        return isset($units[$code]) ? [$units[$code], null] : [null, $unit];
    }

    private function notes(ExtractedRecipe $extracted, string $locale): ?string
    {
        // There's no total-time column; keep it where the reader will see it.
        if ($extracted->totalMinutes && ! $extracted->prepMinutes && ! $extracted->cookMinutes) {
            return __('recipe.import.total_time', ['minutes' => $extracted->totalMinutes], $locale);
        }

        return null;
    }

    private function attachCover(RecipeRevision $revision, string $imageUrl): void
    {
        try {
            $image = $this->fetcher->fetchImage($imageUrl);
            $path = 'recipe-images/'.Str::uuid().'.'.$image['extension'];

            Storage::disk('public')->put($path, $image['bytes']);

            $revision->images()->create([
                'path' => $path,
                'alt_text' => $revision->title,
                'is_cover' => true,
                'sort_order' => 1,
            ]);
        } catch (Throwable $e) {
            Log::info('Recipe import skipped the photo', ['url' => $imageUrl, 'error' => $e->getMessage()]);
        }
    }

    /**
     * @param  array<int, ?string>  $parts
     */
    private function join(array $parts): ?string
    {
        $text = implode(', ', array_filter($parts));

        return $text === '' ? null : mb_substr($text, 0, 255);
    }
}
