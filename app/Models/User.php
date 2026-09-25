<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use App\Support\SupportedLocales;
use Database\Factories\UserFactory;
use Filament\Facades\Filament;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Laravel\Jetstream\HasTeams;

#[Fillable(['name', 'email', 'password', 'locale'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser, HasLocalePreference
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasTeams, HasUuid, Notifiable;

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

    /**
     * The one account that curates the shared catalog: it alone edits units, and the
     * ingredients it creates are visible to everyone rather than private to it.
     */
    /** @var array<int, int>|null Memo for familyMemberIds(). */
    private ?array $familyMemberIds = null;

    public const CATALOG_ADMIN_EMAIL = 'ek@itomat.se';

    public function canAccessPanel(Panel $panel): bool
    {
        return true;
    }

    public function isCatalogAdmin(): bool
    {
        return strcasecmp((string) $this->email, self::CATALOG_ADMIN_EMAIL) === 0;
    }

    /**
     * Jetstream's family pages show an avatar per member. Profile photos are off, so they get
     * the same generated avatar the panel shows.
     */
    public function getProfilePhotoUrlAttribute(): string
    {
        return Filament::getUserAvatarUrl($this);
    }

    /**
     * Everyone the user shares a family with, the user included: whose private ingredients
     * they can see and edit.
     *
     * Remembered for the life of this instance: the ingredients list asks once per row.
     *
     * @return array<int, int>
     */
    public function familyMemberIds(): array
    {
        if ($this->familyMemberIds !== null) {
            return $this->familyMemberIds;
        }

        $familyIds = $this->allTeams()->modelKeys();

        return $this->familyMemberIds = DB::table('team_user')->whereIn('team_id', $familyIds)->pluck('user_id')
            ->merge(DB::table('teams')->whereIn('id', $familyIds)->pluck('user_id'))
            ->push($this->getKey())
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
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
