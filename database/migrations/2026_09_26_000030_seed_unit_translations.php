<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * A name and an abbreviation per locale for the units seeded by 2026_09_21_000030. The
     * abbreviation is what an ingredient line shows ("2 tbsp"). A krm is exactly one ml, so in
     * English it reads as ml and the quantity stays true. Units that don't exist, or already
     * have a translation in that locale, are skipped.
     *
     * @var array<string, array<string, array{0: string, 1: string}>>
     */
    private array $translations = [
        'liter' => ['en' => ['liter', 'l'], 'sv' => ['liter', 'l']],
        'dl' => ['en' => ['deciliter', 'dl'], 'sv' => ['deciliter', 'dl']],
        'cl' => ['en' => ['centiliter', 'cl'], 'sv' => ['centiliter', 'cl']],
        'ml' => ['en' => ['milliliter', 'ml'], 'sv' => ['milliliter', 'ml']],
        'msk' => ['en' => ['tablespoon', 'tbsp'], 'sv' => ['matsked', 'msk']],
        'tsk' => ['en' => ['teaspoon', 'tsp'], 'sv' => ['tesked', 'tsk']],
        'krm' => ['en' => ['spice measure (1 ml)', 'ml'], 'sv' => ['kryddmått', 'krm']],
        'g' => ['en' => ['gram', 'g'], 'sv' => ['gram', 'g']],
        'kg' => ['en' => ['kilogram', 'kg'], 'sv' => ['kilogram', 'kg']],
        'st' => ['en' => ['piece', 'pc'], 'sv' => ['styck', 'st']],
        'förp' => ['en' => ['package', 'pkg'], 'sv' => ['förpackning', 'förp']],
        'burk' => ['en' => ['can', 'can'], 'sv' => ['burk', 'burk']],
        'paket' => ['en' => ['packet', 'packet'], 'sv' => ['paket', 'paket']],
        'nypa' => ['en' => ['pinch', 'pinch'], 'sv' => ['nypa', 'nypa']],
    ];

    public function up(): void
    {
        $units = DB::table('units')->pluck('id', 'code');

        foreach ($this->translations as $code => $locales) {
            if (! isset($units[$code])) {
                continue;
            }

            foreach ($locales as $locale => [$name, $abbreviation]) {
                DB::table('unit_translations')->insertOrIgnore([
                    'unit_id' => $units[$code],
                    'locale' => $locale,
                    'name' => $name,
                    'abbreviation' => $abbreviation,
                ]);
            }
        }
    }

    public function down(): void
    {
        // The table goes with the previous migration; nothing here outlives it.
    }
};
