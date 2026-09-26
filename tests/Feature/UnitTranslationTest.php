<?php

namespace Tests\Feature;

use App\Filament\Resources\RecipeRevisions\Pages\EditRecipeRevision;
use App\Filament\Resources\RecipeRevisions\Pages\ViewRecipeRevision;
use App\Filament\Resources\RecipeRevisions\Schemas\RecipeRevisionForm;
use App\Filament\Resources\Units\Pages\CreateUnit;
use App\Models\Ingredient;
use App\Models\Recipe;
use App\Models\RecipeRevision;
use App\Models\Unit;
use App\Models\User;
use App\Support\SupportedLocales;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Unit codes are Swedish (msk, st, förp); every unit carries a name and abbreviation per
 * supported locale so an English recipe reads "2 tbsp" rather than "2 msk".
 */
class UnitTranslationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('app');
    }

    private function revision(string $locale, string $status = 'draft'): RecipeRevision
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $recipe = Recipe::create(['owner_user_id' => $user->id, 'default_locale' => $locale, 'visibility' => 'public']);

        $revision = $recipe->revisions()->create([
            'locale' => $locale,
            'version_number' => 1,
            'status' => $status,
            'title' => 'Pannkakor',
            'created_by_user_id' => $user->id,
            'published_at' => $status === 'published' ? now() : null,
        ]);

        $revision->ingredients()->create([
            'group_id' => $revision->ingredientGroups()->create(['sort_order' => 1])->id,
            'ingredient_id' => Ingredient::create(['canonical_name' => 'sugar'])->id,
            'unit_id' => Unit::where('code', 'msk')->value('id'),
            'quantity' => 2,
            'sort_order' => 1,
        ]);

        return $revision;
    }

    public function test_every_unit_is_translated_into_every_supported_locale(): void
    {
        $this->assertGreaterThan(0, Unit::count());
        $this->assertEmpty(Unit::missingTranslations());

        $this->artisan('units:check-translations')->assertSuccessful();
    }

    public function test_a_unit_missing_a_translation_is_reported(): void
    {
        $unit = Unit::create(['code' => 'kopp', 'type' => 'volume', 'factor_to_base' => 4]);
        $unit->translations()->create(['locale' => 'sv', 'name' => 'kopp', 'abbreviation' => 'kopp']);

        $this->assertSame(['kopp' => ['en']], Unit::missingTranslations()->all());

        $this->artisan('units:check-translations')->assertFailed();
    }

    public function test_a_unit_reads_in_the_locale_asked_for_and_falls_back_to_its_code(): void
    {
        $tablespoon = Unit::where('code', 'msk')->firstOrFail();

        $this->assertSame('tbsp', $tablespoon->abbreviationFor('en'));
        $this->assertSame('tablespoon', $tablespoon->nameFor('en'));
        $this->assertSame('tbsp (tablespoon)', $tablespoon->labelFor('en'));
        $this->assertSame('msk (matsked)', $tablespoon->labelFor('sv'));
        $this->assertSame('msk', $tablespoon->abbreviationFor('de'));
        $this->assertSame('tbsp', $tablespoon->abbreviationFor('de', 'en'));

        // Name and abbreviation that read the same show once.
        $this->assertSame('pinch', Unit::where('code', 'nypa')->firstOrFail()->labelFor('en'));
    }

    public function test_ingredient_lines_use_the_revisions_locale(): void
    {
        $english = $this->revision('en');
        $swedish = $this->revision('sv');

        $this->assertSame('2 tbsp sugar', $english->ingredients->first()->line());
        $this->assertSame('2 msk sugar', $swedish->ingredients->first()->line());

        $line = ['ingredient_id' => $english->ingredients->first()->ingredient_id, 'unit_id' => $english->ingredients->first()->unit_id, 'quantity' => '2'];
        $this->assertSame('2 tbsp sugar', RecipeRevisionForm::ingredientLine($line, 'en'));
        $this->assertSame('2 msk sugar', RecipeRevisionForm::ingredientLine($line, 'sv'));
    }

    public function test_the_editor_and_view_name_units_in_the_revisions_locale(): void
    {
        $revision = $this->revision('en');

        Livewire::test(EditRecipeRevision::class, ['record' => $revision->getKey()])
            ->assertOk()
            ->assertSee('2 tbsp sugar');

        $unitId = $revision->ingredients->first()->unit_id;
        $this->assertSame('tbsp (tablespoon)', RecipeRevisionForm::unitOptions('en')[$unitId]);
        $this->assertSame('msk (matsked)', RecipeRevisionForm::unitOptions('sv')[$unitId]);

        Livewire::test(ViewRecipeRevision::class, ['record' => $revision->getKey()])
            ->assertOk()
            ->assertSee('2 tbsp sugar');
    }

    public function test_the_public_page_names_units_in_the_revisions_locale(): void
    {
        $revision = $this->revision('en', 'published');

        $this->get($revision->recipe->shareUrl())
            ->assertOk()
            ->assertSee('2 tbsp sugar')
            ->assertDontSee('2 msk sugar');
    }

    public function test_a_new_unit_needs_a_translation_for_each_supported_locale(): void
    {
        $this->actingAs(User::factory()->create(['email' => User::CATALOG_ADMIN_EMAIL]));

        $form = ['code' => 'kopp', 'type' => 'volume', 'factor_to_base' => 4];

        // Starts with a row per locale; leaving one blank is not allowed.
        Livewire::test(CreateUnit::class)
            ->assertCount('data.translations', count(SupportedLocales::codes()))
            ->fillForm([...$form, 'translations' => [['locale' => 'sv', 'name' => 'kopp', 'abbreviation' => 'kopp']]])
            ->call('create')
            ->assertHasFormErrors(['translations']);

        // Nor is the same locale twice.
        Livewire::test(CreateUnit::class)
            ->fillForm([...$form, 'translations' => [
                ['locale' => 'sv', 'name' => 'kopp', 'abbreviation' => 'kopp'],
                ['locale' => 'sv', 'name' => 'kopp', 'abbreviation' => 'kopp'],
            ]])
            ->call('create')
            ->assertHasFormErrors();

        Livewire::test(CreateUnit::class)
            ->fillForm([...$form, 'translations' => [
                ['locale' => 'en', 'name' => 'cup', 'abbreviation' => 'cup'],
                ['locale' => 'sv', 'name' => 'kopp', 'abbreviation' => 'kopp'],
            ]])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame('cup', Unit::where('code', 'kopp')->firstOrFail()->abbreviationFor('en'));
        $this->assertEmpty(Unit::missingTranslations());
    }
}
