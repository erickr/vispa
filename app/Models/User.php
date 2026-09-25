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
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
    /** @var array<int, int>|null Memo for householdMemberIds(). */
    private ?array $householdMemberIds = null;

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
     * Everyone the user shares a household with, the user included: whose private ingredients
     * they can see and edit. Remembered for the life of this instance: the ingredients list
     * asks once per row.
     *
     * @return array<int, int>
     */
    public function householdMemberIds(): array
    {
        if ($this->householdMemberIds !== null) {
            return $this->householdMemberIds;
        }

        $householdIds = $this->allTeams()->modelKeys();

        return $this->householdMemberIds = DB::table('household_user')->whereIn('household_id', $householdIds)->pluck('user_id')
            ->merge(DB::table('households')->whereIn('id', $householdIds)->pluck('user_id'))
            ->push($this->getKey())
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /*
     * Jetstream's HasTeams, with our column names. Its method names stay (the rest of the trait
     * and Jetstream's actions call them); these three are the ones that hardcode
     * `current_household_id` and `personal_household`.
     */

    public function currentTeam(): BelongsTo
    {
        if ($this->current_household_id === null && $this->id) {
            $this->switchTeam($this->personalTeam());
        }

        return $this->belongsTo(Household::class, 'current_household_id');
    }

    /**
     * @param  Household|null  $team
     */
    public function switchTeam($team): bool
    {
        if (! $team || ! $this->belongsToTeam($team)) {
            return false;
        }

        $this->forceFill(['current_household_id' => $team->id])->save();
        $this->setRelation('currentTeam', $team);

        return true;
    }

    public function personalTeam(): ?Household
    {
        return $this->ownedTeams->where('personal_household', true)->first();
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
