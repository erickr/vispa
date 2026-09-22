<?php

namespace App\Filament\Actions;

use App\Models\Recipe;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Contracts\HasTable;
use Livewire\Component;

/**
 * Hands out a recipe's public link — and, when the recipe is private, asks first.
 *
 * A private recipe has no page to link to, so the modal explains what unlisted means and offers
 * to switch. Only once the owner agrees does the link appear: the same action re-opens, now on a
 * shareable recipe, showing the URL.
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
            ->modalHeading(fn (Recipe $record): string => $record->isShareable()
                ? __('recipe.share.heading')
                : __('recipe.share.private_heading'))
            ->modalDescription(fn (Recipe $record): string => $record->isShareable()
                ? __('recipe.share.description')
                : __('recipe.share.private_description'))
            ->modalContent(fn (Recipe $record) => $record->isShareable()
                ? view('filament.recipes.share-link', [
                    'url' => $record->shareUrl(),
                    'isDraft' => $record->sharedRevisions()->every(fn ($revision): bool => $revision->status !== 'published'),
                ])
                : null)
            // Nothing to submit once the link is on screen: the modal is the answer.
            ->modalSubmitAction(fn (Recipe $record) => $record->isShareable() ? false : null)
            ->modalSubmitActionLabel(__('recipe.share.make_unlisted'))
            ->modalCancelActionLabel(fn (Recipe $record): string => $record->isShareable()
                ? __('recipe.share.close')
                : __('filament-actions::modal.actions.cancel.label'))
            ->action(function (Recipe $record, Component $livewire) use ($name): void {
                if ($record->isShareable()) {
                    return;
                }

                $record->update(['visibility' => 'unlisted']);

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
}
