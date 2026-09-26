<?php

namespace App\Models;

use App\Support\SupportedLocales;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class Unit extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'code',
        'type',
        'base_unit_id',
        'factor_to_base',
    ];

    protected function casts(): array
    {
        return [
            'factor_to_base' => 'decimal:6',
        ];
    }

    public function baseUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'base_unit_id');
    }

    public function derivedUnits(): HasMany
    {
        return $this->hasMany(Unit::class, 'base_unit_id');
    }

    public function translations(): HasMany
    {
        return $this->hasMany(UnitTranslation::class);
    }

    /**
     * The spelled-out name, for pickers: "tablespoon". Falls back to the code.
     */
    public function nameFor(string $locale, ?string $fallback = null): string
    {
        return $this->translationFor($locale, $fallback)?->name ?? $this->code;
    }

    /**
     * What an ingredient line shows: "2 tbsp". Falls back to the code.
     */
    public function abbreviationFor(string $locale, ?string $fallback = null): string
    {
        return $this->translationFor($locale, $fallback)?->abbreviation ?? $this->code;
    }

    /**
     * A picker option: "tbsp (tablespoon)", or just "can" when the two read the same.
     */
    public function labelFor(string $locale): string
    {
        $abbreviation = $this->abbreviationFor($locale);
        $name = $this->nameFor($locale);

        return $abbreviation === $name ? $name : "{$abbreviation} ({$name})";
    }

    protected function translationFor(string $locale, ?string $fallback): ?UnitTranslation
    {
        return $this->translations->firstWhere('locale', $locale)
            ?? ($fallback ? $this->translations->firstWhere('locale', $fallback) : null);
    }

    /**
     * Every unit must read naturally in every supported locale. Unit code => the locales it is
     * still missing, empty when the catalog is complete.
     *
     * @return Collection<string, array<int, string>>
     */
    public static function missingTranslations(): Collection
    {
        return static::query()
            ->with('translations')
            ->orderBy('code')
            ->get()
            ->mapWithKeys(fn (Unit $unit): array => [
                $unit->code => array_values(array_diff(SupportedLocales::codes(), $unit->translations->pluck('locale')->all())),
            ])
            ->filter();
    }
}
