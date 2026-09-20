<?php

namespace App\Filament\Pages\Auth;

use App\Support\SupportedLocales;
use Filament\Auth\Pages\EditProfile as BaseEditProfile;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;

class EditProfile extends BaseEditProfile
{
    public function form(Schema $schema): Schema
    {
        return $schema->components([
            $this->getNameFormComponent(),
            $this->getEmailFormComponent(),
            $this->getLocaleFormComponent(),
            $this->getPasswordFormComponent(),
            $this->getPasswordConfirmationFormComponent(),
            $this->getCurrentPasswordFormComponent(),
        ]);
    }

    protected function getLocaleFormComponent(): Component
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

    /**
     * The locale for this request was already set when the middleware ran, so a
     * save that switches language would otherwise render the old one. Bounce
     * back to the page and the next request picks up the new locale.
     */
    protected function getRedirectUrl(): ?string
    {
        return static::getUrl();
    }
}
