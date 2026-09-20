<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use App\Support\SupportedLocales;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'locale'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser, HasLocalePreference
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasUuid, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Used by Laravel when queueing notifications, and by Filament's own
     * email-change notices, so mail matches the language the user picked.
     */
    public function preferredLocale(): string
    {
        return SupportedLocales::sanitize($this->locale);
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return true;
    }

    public function recipes(): HasMany
    {
        return $this->hasMany(Recipe::class, 'owner_user_id');
    }

    public function authoredRevisions(): HasMany
    {
        return $this->hasMany(RecipeRevision::class, 'created_by_user_id');
    }
}
