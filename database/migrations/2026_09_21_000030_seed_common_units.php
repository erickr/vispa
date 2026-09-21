<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The Swedish kitchen units recipe imports meet most often. factor_to_base is how many of the
     * unit make one base unit (1 liter = 10 dl), matching the existing rows. Codes that already
     * exist are left untouched.
     *
     * @var array<int, array{0: string, 1: string, 2: ?string, 3: float}>
     */
    private array $units = [
        ['liter', 'volume', null, 1],
        ['dl', 'volume', 'liter', 10],
        ['cl', 'volume', 'liter', 100],
        ['ml', 'volume', 'liter', 1000],
        ['msk', 'volume', 'liter', 66.666667],
        ['tsk', 'volume', 'liter', 200],
        ['krm', 'volume', 'liter', 1000],
        ['g', 'mass', null, 1],
        ['kg', 'mass', 'g', 0.001],
        ['st', 'count', null, 1],
        ['förp', 'count', null, 1],
        ['burk', 'count', null, 1],
        ['paket', 'count', null, 1],
        ['nypa', 'count', null, 1],
    ];

    public function up(): void
    {
        foreach ($this->units as [$code, $type, $base, $factor]) {
            if (DB::table('units')->where('code', $code)->exists()) {
                continue;
            }

            DB::table('units')->insert([
                'code' => $code,
                'type' => $type,
                'base_unit_id' => $base ? DB::table('units')->where('code', $base)->value('id') : null,
                'factor_to_base' => $factor,
            ]);
        }
    }

    public function down(): void
    {
        // Only remove what this migration could have added and nothing references.
        $added = ['cl', 'ml', 'msk', 'krm', 'förp', 'burk', 'paket', 'nypa'];

        DB::table('units')
            ->whereIn('code', $added)
            ->whereNotExists(fn ($query) => $query->from('recipe_revision_ingredients')->whereColumn('unit_id', 'units.id'))
            ->delete();
    }
};
