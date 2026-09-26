<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * More kitchen units: the rest of the modern Swedish set, US and imperial measures, and the
     * historical ones older recipes use. The code is the Swedish abbreviation, else the Swedish
     * word. factor_to_base is how many of the unit make one base unit (1 liter = 10 dl), as in
     * 2026_09_21_000030. Units with no fixed size (klyfta, knippa, näve) have no base unit, like
     * burk and nypa. Codes that already exist are left untouched, translations included.
     *
     * code, type, base code, factor_to_base, [sv name, sv abbreviation], [en name, en abbreviation]
     *
     * @var array<int, array{0: string, 1: string, 2: ?string, 3: float, 4: array{0: string, 1: string}, 5: array{0: string, 1: string}}>
     */
    private array $units = [
        // Volume
        ['kkp', 'volume', 'liter', 6.666667, ['kaffekopp', 'kkp'], ['coffee cup', 'coffee cup']],
        ['tekopp', 'volume', 'liter', 5, ['tekopp', 'tekopp'], ['teacup', 'teacup']],
        ['glas', 'volume', 'liter', 5, ['glas', 'glas'], ['glass', 'glass']],
        ['droppe', 'volume', 'liter', 20000, ['droppe', 'droppe'], ['drop', 'drop']],
        ['kopp (amerikansk)', 'volume', 'liter', 4.226500, ['kopp (amerikansk)', 'kopp'], ['cup (US)', 'cup']],
        ['kanna', 'volume', 'liter', 0.382100, ['kanna', 'kanna'], ['Swedish kanna (jug)', 'kanna']],
        ['stop', 'volume', 'liter', 0.763900, ['stop', 'stop'], ['stoup', 'stoup']],
        ['kvarter', 'volume', 'liter', 3.058100, ['kvarter', 'kvarter'], ['quarter', 'quarter']],
        ['jungfru', 'volume', 'liter', 12.224900, ['jungfru', 'jungfru'], ['Swedish jungfru (gill)', 'gill']],

        // Mass
        ['hg', 'mass', 'g', 0.01, ['hektogram', 'hg'], ['hectogram', 'hg']],
        ['mg', 'mass', 'g', 1000, ['milligram', 'mg'], ['milligram', 'mg']],
        ['skålpund', 'mass', 'g', 0.002353, ['skålpund', 'skålpund'], ['Swedish pound', 'Swedish pound']],
        ['lod', 'mass', 'g', 0.075301, ['lod', 'lod'], ['Swedish lod', 'lod']],
        ['kvintin', 'mass', 'g', 0.301205, ['kvintin', 'kvintin'], ['quint', 'quint']],
        ['pound', 'mass', 'g', 0.002205, ['pound', 'lb'], ['pound', 'lb']],
        ['ounce', 'mass', 'g', 0.035273, ['ounce', 'oz'], ['ounce', 'oz']],

        // Counted in pieces
        ['dussin', 'count', 'st', 0.083333, ['dussin', 'dussin'], ['dozen', 'doz']],
        ['tjog', 'count', 'st', 0.05, ['tjog', 'tjog'], ['score', 'score']],
        ['skock', 'count', 'st', 0.016667, ['skock', 'skock'], ['three score', 'three score']],
        ['gross', 'count', 'st', 0.006944, ['gross', 'gross'], ['gross', 'gross']],

        // No fixed size
        ['klyfta', 'count', null, 1, ['klyfta', 'klyfta'], ['clove', 'clove']],
        ['knippa', 'count', null, 1, ['knippa', 'knippa'], ['bunch', 'bunch']],
        ['kvist', 'count', null, 1, ['kvist', 'kvist'], ['sprig', 'sprig']],
        ['blad', 'count', null, 1, ['blad', 'blad'], ['leaf', 'leaf']],
        ['skiva', 'count', null, 1, ['skiva', 'skiva'], ['slice', 'slice']],
        ['bit', 'count', null, 1, ['bit', 'bit'], ['piece', 'piece']],
        ['kruka', 'count', null, 1, ['kruka', 'kruka'], ['pot', 'pot']],
        ['påse', 'count', null, 1, ['påse', 'påse'], ['bag', 'bag']],
        ['flaska', 'count', null, 1, ['flaska', 'flaska'], ['bottle', 'bottle']],
        ['port', 'count', null, 1, ['portion', 'port'], ['serving', 'serving']],
        ['skvätt', 'count', null, 1, ['skvätt', 'skvätt'], ['splash', 'splash']],
        ['näve', 'count', null, 1, ['näve', 'näve'], ['handful', 'handful']],
    ];

    public function up(): void
    {
        foreach ($this->units as [$code, $type, $base, $factor, $sv, $en]) {
            if (DB::table('units')->where('code', $code)->exists()) {
                continue;
            }

            $id = DB::table('units')->insertGetId([
                'code' => $code,
                'type' => $type,
                'base_unit_id' => $base ? DB::table('units')->where('code', $base)->value('id') : null,
                'factor_to_base' => $factor,
            ]);

            foreach (['sv' => $sv, 'en' => $en] as $locale => [$name, $abbreviation]) {
                DB::table('unit_translations')->insert([
                    'unit_id' => $id,
                    'locale' => $locale,
                    'name' => $name,
                    'abbreviation' => $abbreviation,
                ]);
            }
        }
    }

    public function down(): void
    {
        // Only remove what nothing references; translations go with their unit.
        DB::table('units')
            ->whereIn('code', array_column($this->units, 0))
            ->whereNotExists(fn ($query) => $query->from('recipe_revision_ingredients')->whereColumn('unit_id', 'units.id'))
            ->delete();
    }
};
