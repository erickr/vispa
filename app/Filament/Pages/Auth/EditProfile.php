<?php

namespace App\Filament\Pages\Auth;

use App\Filament\Pages\Auth\Concerns\HasLocaleField;
use Filament\Auth\Pages\EditProfile as BaseEditProfile;
use Filament\Schemas\Schema;

class EditProfile extends BaseEditProfile
{
    use HasLocaleField;

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
