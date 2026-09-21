<?php

namespace App\Recipes\Import;

use App\Filament\Resources\RecipeRevisions\Schemas\RecipeRevisionForm;

/**
 * A recipe as the extractor understood it, before it touches the database. Built from the
 * structured-output JSON, so every field is already the right shape — fromArray() only guards
 * against values the schema can't rule out (blank strings, negative numbers).
 */
final class ExtractedRecipe
{
    /**
     * @param  array<int, array{title: ?string, items: array<int, array{raw: ?string, quantity: ?float, quantity_min: ?float, quantity_max: ?float, unit: ?string, ingredient: string, preparation_note: ?string, optional: bool}>}>  $ingredientGroups
     * @param  array<int, array{title: ?string, steps: array<int, array{text: string, timer_seconds: ?int}>}>  $instructionSections
     */
    public function __construct(
        public readonly ?string $title,
        public readonly ?string $description,
        public readonly ?string $locale,
        public readonly ?int $servings,
        public readonly ?int $prepMinutes,
        public readonly ?int $cookMinutes,
        public readonly ?int $totalMinutes,
        public readonly ?string $sourceCredit,
        public readonly ?string $imageUrl,
        public readonly array $ingredientGroups,
        public readonly array $instructionSections,
        public readonly ?string $model = null,
        public readonly ?int $inputTokens = null,
        public readonly ?int $outputTokens = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data, ?string $model = null, ?int $inputTokens = null, ?int $outputTokens = null): self
    {
        $groups = [];

        foreach ((array) ($data['ingredient_groups'] ?? []) as $group) {
            $items = [];

            foreach ((array) ($group['items'] ?? []) as $item) {
                $name = self::text($item['ingredient'] ?? null);

                if ($name === null) {
                    continue;
                }

                $items[] = [
                    'raw' => self::text($item['raw'] ?? null),
                    'quantity' => self::number($item['quantity'] ?? null),
                    'quantity_min' => self::number($item['quantity_min'] ?? null),
                    'quantity_max' => self::number($item['quantity_max'] ?? null),
                    'unit' => self::text($item['unit'] ?? null),
                    'ingredient' => mb_substr($name, 0, 255),
                    'preparation_note' => self::text($item['preparation_note'] ?? null, 255),
                    'optional' => (bool) ($item['optional'] ?? false),
                ];
            }

            if ($items !== []) {
                $groups[] = ['title' => self::text($group['title'] ?? null, 255), 'items' => $items];
            }
        }

        $sections = [];

        foreach ((array) ($data['instruction_sections'] ?? []) as $section) {
            $steps = [];

            foreach ((array) ($section['steps'] ?? []) as $step) {
                if ($text = self::text($step['text'] ?? null)) {
                    $steps[] = ['text' => $text, 'timer_seconds' => self::positiveInt($step['timer_seconds'] ?? null)];
                }
            }

            if ($steps !== []) {
                $sections[] = ['title' => self::text($section['title'] ?? null, 255), 'steps' => $steps];
            }
        }

        return new self(
            title: self::text($data['title'] ?? null, 255),
            description: self::text($data['description'] ?? null),
            locale: self::text($data['locale'] ?? null),
            servings: self::positiveInt($data['servings'] ?? null),
            prepMinutes: self::positiveInt($data['prep_minutes'] ?? null),
            cookMinutes: self::positiveInt($data['cook_minutes'] ?? null),
            totalMinutes: self::positiveInt($data['total_minutes'] ?? null),
            sourceCredit: self::text($data['source_credit'] ?? null, 255),
            imageUrl: filter_var($data['image_url'] ?? null, FILTER_VALIDATE_URL) ?: null,
            ingredientGroups: $groups,
            instructionSections: $sections,
            model: $model,
            inputTokens: $inputTokens,
            outputTokens: $outputTokens,
        );
    }

    private static function text(mixed $value, ?int $limit = null): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        return $limit ? mb_substr($value, 0, $limit) : $value;
    }

    private static function number(mixed $value): ?float
    {
        $number = is_numeric($value) ? (float) $value : RecipeRevisionForm::parseQuantity($value);

        return $number !== null && $number >= 0 && $number < 10_000_000 ? round($number, 3) : null;
    }

    private static function positiveInt(mixed $value): ?int
    {
        return is_numeric($value) && (int) $value > 0 ? (int) $value : null;
    }
}
