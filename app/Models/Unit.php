<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
}
