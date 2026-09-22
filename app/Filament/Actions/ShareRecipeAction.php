<?php

namespace App\Filament\Actions;

use App\Models\Recipe;
use App\Models\RecipeRevision;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Contracts\HasTable;
use Illuminate\Database\Eloquent\Model;
use Livewire\Component;

/**
 * Hands out a recipe's public link — and, when the recipe is private, asks first.
 *
 * A private recipe has no page to link to, so the modal explains what unlisted means and offers
 * to switch. Only once the owner agrees does the link appear: the same action re-opens, now on a
 * shareable recipe, showing the URL.
 *
 * Sharing is a property of the recipe, but the pages that offer it are not all recipe pages —
 * a revision is what the cook is looking at when they decide to pass it on — so the action takes
 * either record and works on the recipe behind it.
 */
class ShareRecipeAction
{
    public static function make(string $name = 'share'): Action
    {
        return Action::make($name)
            ->label(__('recipe.share.action'))
            ->icon(Heroicon::OutlinedShare)
            ->color('gray')
            ->modalIcon(Heroicon::OutlinedShare)
            ->modalHeading(fn (Model $record): string => static::recipe($record)->isShareable()
                ? __('recipe.share.heading')
                : __('recipe.share.private_heading'))
            ->modalDescription(fn (Model $record): string => static::recipe($record)->isShareable()
                ? __('recipe.share.description')
                : __('recipe.share.private_description'))
            ->modalContent(function (Model $record) {
                $recipe = static::recipe($record);

                return $recipe->isShareable()
                    ? view('filament.recipes.share-link', [
                        'url' => $recipe->shareUrl(),
                        'isDraft' => $recipe->sharedRevisions()->every(fn (RecipeRevision $revision): bool => $revision->status !== 'published'),
                    ])
                    : null;
            })
            // Nothing to submit once the link is on screen: the modal is the answer.
            ->modalSubmitAction(fn (Model $record) => static::recipe($record)->isShareable() ? false : null)
            ->modalSubmitActionLabel(__('recipe.share.make_unlisted'))
            ->modalCancelActionLabel(fn (Model $record): string => static::recipe($record)->isShareable()
                ? __('recipe.share.close')
                : __('filament-actions::modal.actions.cancel.label'))
            ->action(function (Model $record, Component $livewire) use ($name): void {
                $recipe = static::recipe($record);

                if ($recipe->isShareable()) {
                    return;
                }

                $recipe->update(['visibility' => 'unlisted']);

                Notification::make()
                    ->title(__('recipe.share.now_unlisted.title'))
                    ->body(__('recipe.share.now_unlisted.body'))
                    ->success()
                    ->send();

                // Re-open on the now-unlisted recipe, which is the branch that shows the link.
                // The extra context key is what marks this as a replacement rather than the same
                // modal — Filament compares name and context to decide whether to unmount.
                $livewire->replaceMountedAction($name, context: [
                    ...($livewire instanceof HasTable ? ['table' => true, 'recordKey' => $record->getKey()] : []),
                    'shared' => true,
                ]);
            });
    }

    private static function recipe(Model $record): Recipe
    {
        return $record instanceof RecipeRevision ? $record->recipe : $record;
    }
}
