<?php

namespace App\Recipes\Import;

use Carbon\CarbonInterval;
use Filament\Notifications\Notification;
use RuntimeException;

/**
 * Thrown by ImportRecipe::start() when the user has used up their import budget, before anything
 * is recorded or queued. Carries how long until the next import is allowed.
 */
class ImportLimitReached extends RuntimeException
{
    public function __construct(public readonly int $availableIn)
    {
        parent::__construct("Recipe import limit reached; available again in {$availableIn}s.");
    }

    /** How the panel says so, in the current locale. */
    public function notification(): Notification
    {
        return Notification::make()
            ->title(__('recipe.import.limited.title'))
            ->body(__('recipe.import.limited.body', [
                'time' => CarbonInterval::seconds(max(1, $this->availableIn))->cascade()->forHumans(['parts' => 1]),
            ]))
            ->danger()
            ->persistent();
    }
}
