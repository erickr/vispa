<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UnitTranslation extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'unit_id',
        'locale',
        'name',
        'abbreviation',
    ];

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }
}
