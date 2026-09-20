<?php

namespace Tests\Feature;

use App\Filament\Resources\Ingredients\Pages\CreateIngredient;
use App\Filament\Resources\RecipeRevisions\Pages\EditRecipeRevision;
use App\Filament\Resources\Recipes\Pages\CreateRecipe;
use App\Filament\Resources\Recipes\Pages\EditRecipe;
use App\Models\Recipe;
use App\Models\User;
use App\Support\SupportedLocales;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Locale is picked from a list, not typed. The list is what Vispa speaks, and a new row starts
 * on whatever language the author reads the panel in.
 */
class LocalePickerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('app');
    }

    private function owner(string $locale = 'en'): User
    {
        $user = User::factory()->create(['locale' => $locale]);
        $this->actingAs($user);
        $this->app->setLocale($locale);

        return $user;
    }

    public function test_a_new_recipe_starts_on_the_authors_language(): void
    {
        $this->owner('sv');

        Livewire::test(CreateRecipe::class)
            ->assertSchemaStateSet(['default_locale' => 'sv'])
            ->call('create')
            ->assertHasNoFormErrors();

        $recipe = Recipe::sole();

        $this->assertSame('sv', $recipe->default_locale);
        // The seeded first revision follows the recipe, so it lands in Swedish too.
        $this->assertSame('sv', $recipe->revisions()->sole()->locale);
    }

    public function test_an_english_author_still_starts_on_english(): void
    {
        $this->owner('en');

        Livewire::test(CreateRecipe::class)
            ->assertSchemaStateSet(['default_locale' => 'en'])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame('en', Recipe::sole()->default_locale);
    }

    public function test_a_locale_outside_the_list_is_rejected(): void
    {
        $this->owner('sv');

        Livewire::test(CreateRecipe::class)
            ->fillForm(['default_locale' => 'de'])
            ->call('create')
            ->assertHasFormErrors(['default_locale']);

        $this->assertSame(0, Recipe::count());
    }

    public function test_an_existing_row_in_an_unlisted_locale_keeps_its_value(): void
    {
        $user = $this->owner('en');

        // Seeded before German was taken off the list — opening the editor must not quietly
        // rewrite it, and saving must not fail validation.
        $recipe = Recipe::create([
            'owner_user_id' => $user->id,
            'default_locale' => 'de',
            'visibility' => 'private',
        ]);
        $revision = $recipe->revisions()->create([
            'locale' => 'de',
            'version_number' => 1,
            'status' => 'draft',
            'title' => 'Pfannkuchen',
            'created_by_user_id' => $user->id,
        ]);

        Livewire::test(EditRecipe::class, ['record' => $recipe->getKey()])
            ->assertSchemaStateSet(['default_locale' => 'de'])
            ->call('save')
            ->assertHasNoFormErrors();

        Livewire::test(EditRecipeRevision::class, ['record' => $revision->getKey()])
            ->assertSchemaStateSet(['locale' => 'de'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('de', $recipe->fresh()->default_locale);
        $this->assertSame('de', $revision->fresh()->locale);
    }

    /**
     * Rows added inside a repeater get the same preselection. They are added the way the button
     * does it, so the field defaults actually run; Filament keys the new row by uuid.
     *
     * @return array<string, mixed>
     */
    private function addRepeaterRow(string $page, string $repeater, array $formData = []): array
    {
        $state = Livewire::test($page)
            ->fillForm($formData)
            ->call('mountAction', 'add', [], ['schemaComponent' => "form.{$repeater}"])
            ->get('data');

        $this->assertNotEmpty($state[$repeater], "No row was added to [{$repeater}].");

        return Arr::first($state[$repeater]);
    }

    public function test_a_new_catalog_translation_starts_on_the_authors_language(): void
    {
        $this->owner('sv');

        $row = $this->addRepeaterRow(CreateIngredient::class, 'translations', ['canonical_name' => 'vetemjöl']);

        $this->assertSame('sv', $row['locale']);
    }

    public function test_new_revision_and_slug_rows_start_on_the_authors_language(): void
    {
        $this->owner('sv');

        $this->assertSame('sv', $this->addRepeaterRow(CreateRecipe::class, 'revisions')['locale']);
        $this->assertSame('sv', $this->addRepeaterRow(CreateRecipe::class, 'localeSlugs')['locale']);
    }

    public function test_the_picker_offers_an_unlisted_locale_alongside_the_supported_ones(): void
    {
        $this->assertSame(
            ['en' => 'English', 'sv' => 'Svenska'],
            SupportedLocales::optionsIncluding(null),
        );

        $this->assertSame(
            ['en' => 'English', 'sv' => 'Svenska', 'de' => 'de'],
            SupportedLocales::optionsIncluding('de'),
        );

        // A supported locale is not duplicated.
        $this->assertSame(
            ['en' => 'English', 'sv' => 'Svenska'],
            SupportedLocales::optionsIncluding('sv'),
        );
    }

    public function test_the_preselected_locale_falls_back_when_nobody_is_signed_in(): void
    {
        $this->assertSame('en', SupportedLocales::preferred());

        $this->actingAs(User::factory()->create(['locale' => 'sv']));

        $this->assertSame('sv', SupportedLocales::preferred());
    }
}
