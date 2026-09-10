<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecipeRevisionIngredient extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'recipe_revision_id',
        'group_id',
        'ingredient_id',
        'quantity',
        'quantity_min',
        'quantity_max',
        'unit_id',
        'optional',
        'preparation_note',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'quantity_min' => 'decimal:3',
            'quantity_max' => 'decimal:3',
            'optional' => 'boolean',
        ];
    }

    /**
     * The line as it reads on paper: "150 g butter, softened". Shared by the editor's collapsed
     * repeater label and the read-only view so the two can never drift apart.
     */
    public static function formatLine(
        ?string $quantity,
        ?string $unit,
        ?string $name,
        ?string $preparationNote = null,
        bool $optional = false,
    ): string {
        $line = trim(implode(' ', array_filter([$quantity, $unit, $name])));

        if (filled($preparationNote)) {
            $line .= ', '.$preparationNote;
        }

        if ($optional) {
            $line .= ' (optional)';
        }

        return $line;
    }

    /**
     * Trim the decimal(10,3) storage back to something readable: 1.500 -> 1.5, 150.000 -> 150.
     */
    public static function formatQuantity(mixed $quantity): ?string
    {
        if ($quantity === null || $quantity === '') {
            return null;
        }

        if (! is_numeric($quantity)) {
            return (string) $quantity;
        }

        $value = rtrim(rtrim(number_format((float) $quantity, 3, '.', ''), '0'), '.');

        return $value === '' ? null : $value;
    }

    public function line(): string
    {
        return static::formatLine(
            static::formatQuantity($this->quantity),
            $this->unit?->code,
            $this->ingredient?->canonical_name,
            $this->preparation_note,
            (bool) $this->optional,
        );
    }

    public function revision(): BelongsTo
    {
        return $this->belongsTo(RecipeRevision::class, 'recipe_revision_id');
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(RecipeRevisionIngredientGroup::class, 'group_id');
    }

    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }
}
