<?php

namespace App\Filament\Pages\Auth;

use App\Filament\Pages\Auth\Concerns\HasLocaleField;
use App\Support\PublicLocale;
use Filament\Auth\Pages\Register as BaseRegister;
use Filament\Forms\Components\Select;
use Filament\Schemas\Schema;

/**
 * Asks for the language up front, so the verification mail and the personal household's name
 * are already in it. It starts on whatever the visitor was reading the public pages in — the
 * landing page's toggle, else their browser.
 */
class Register extends BaseRegister
{
    use HasLocaleField {
        getLocaleFormComponent as baseLocaleFormComponent;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            $this->getNameFormComponent(),
            $this->getEmailFormComponent(),
            $this->getLocaleFormComponent(),
            $this->getPasswordFormComponent(),
            $this->getPasswordConfirmationFormComponent(),
        ]);
    }

    protected function getLocaleFormComponent(): Select
    {
        return $this->baseLocaleFormComponent()->default(fn (): string => PublicLocale::resolve(request()));
    }
}
