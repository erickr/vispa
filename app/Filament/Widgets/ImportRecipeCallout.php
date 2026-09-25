<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Recipes\RecipeResource;
use Filament\Widgets\Widget;

/**
 * An invitation to bring a recipe in from the web, with why it's worth it. Opens the recipe
 * list with the import modal already up, the same way the sidebar link does. Hidden when
 * importing isn't configured (no Anthropic key), like every other way in.
 */
class ImportRecipeCallout extends Widget
{
    protected string $view = 'filament.widgets.import-recipe-callout';

    protected static ?int $sort = 0;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return filled(config('services.anthropic.key'));
    }

    public function importUrl(): string
    {
        return RecipeResource::getUrl('index').'?action=importFromLink';
    }

    /**
     * @return array<int, array{icon: string, title: string, body: string}>
     */
    public function benefits(): array
    {
        return [
            ['icon' => 'heroicon-o-book-open', 'title' => __('recipe.import.callout.read_title'), 'body' => __('recipe.import.callout.read_body')],
            ['icon' => 'heroicon-o-magnifying-glass', 'title' => __('recipe.import.callout.find_title'), 'body' => __('recipe.import.callout.find_body')],
            ['icon' => 'heroicon-o-pencil-square', 'title' => __('recipe.import.callout.notes_title'), 'body' => __('recipe.import.callout.notes_body')],
        ];
    }
}
