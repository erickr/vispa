<?php

namespace App\Filament\Pages\Auth\Concerns;

use App\Support\SupportedLocales;
use Filament\Forms\Components\Select;

/**
 * The language picker shared by the profile and the registration form, both saving to
 * users.locale.
 */
trait HasLocaleField
{
    protected function getLocaleFormComponent(): Select
    {
        return Select::make('locale')
            ->label(__('profile.locale.label'))
            ->helperText(__('profile.locale.helper_text'))
            ->options(SupportedLocales::options())
            ->default(SupportedLocales::DEFAULT)
            ->selectablePlaceholder(false)
            ->native(false)
            ->required()
            ->in(SupportedLocales::codes());
    }
}
