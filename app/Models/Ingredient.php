<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ingredient extends Model
{
    use HasUuid;

    protected $fillable = [
        'uuid',
        'canonical_name',
    ];

    public function translations(): HasMany
    {
        return $this->hasMany(IngredientTranslation::class);
    }

    public function nameFor(string $locale, ?string $fallback = null): string
    {
        $translation = $this->translations
            ->firstWhere('locale', $locale)
            ?? ($fallback ? $this->translations->firstWhere('locale', $fallback) : null);

        return $translation?->name ?? $this->canonical_name;
    }
}
