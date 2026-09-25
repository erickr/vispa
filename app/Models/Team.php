<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Database\Factories\TeamFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Str;
use Laravel\Jetstream\Events\TeamCreated;
use Laravel\Jetstream\Events\TeamDeleted;
use Laravel\Jetstream\Events\TeamUpdated;
use Laravel\Jetstream\Team as JetstreamTeam;

/**
 * A family: Jetstream's team, under the name the app gives it. Each user owns one (their
 * "personal team", created on sign-up) and can invite the rest of the household into it.
 */
class Team extends JetstreamTeam
{
    /** @use HasFactory<TeamFactory> */
    use HasFactory, HasUuid;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'personal_team',
    ];

    /**
     * The event map for the model.
     *
     * @var array<string, class-string>
     */
    protected $dispatchesEvents = [
        'created' => TeamCreated::class,
        'updated' => TeamUpdated::class,
        'deleted' => TeamDeleted::class,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'personal_team' => 'boolean',
        ];
    }

    /**
     * "The Krona family" for Eric Krona: the last word of the name is taken as the surname,
     * which is right more often than not and is only a starting point — the owner can rename it.
     */
    public static function familyNameFor(string $name, ?string $locale = null): string
    {
        $surname = Str::of($name)->squish()->explode(' ')->last();

        return __('family.default_name', ['surname' => $surname !== '' ? $surname : $name], $locale);
    }
}
