<?php

namespace Tests\Feature;

use App\Filament\Resources\Ingredients\IngredientResource;
use App\Filament\Resources\RecipeRevisions\Pages\EditRecipeRevision;
use App\Filament\Resources\RecipeRevisions\Pages\ViewRecipeRevision;
use App\Filament\Resources\Recipes\Pages\CreateRecipe;
use App\Filament\Resources\Recipes\Pages\ListRecipes;
use App\Filament\Resources\Recipes\RecipeResource;
use App\Models\Ingredient;
use App\Models\Recipe;
use App\Models\RecipeRevision;
use App\Models\RecipeRevisionIngredient;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The panel is read in whichever language the user picked on their profile. These tests set the
 * locale directly — in a real request SetUserLocale does it — and check that the chrome follows.
 */
class PanelTranslationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('app');
    }

    private function owner(): User
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        return $user;
    }

    private function recipeFor(User $user, ?string $sourceUrl = null): Recipe
    {
        return Recipe::create([
            'owner_user_id' => $user->id,
            'default_locale' => 'en',
            'visibility' => 'private',
            'source_url' => $sourceUrl,
        ]);
    }

    public function test_every_english_key_has_a_swedish_counterpart(): void
    {
        foreach (File::files(lang_path('en')) as $file) {
            $name = $file->getFilenameWithoutExtension();
            $swedish = lang_path("sv/{$name}.php");

            $this->assertFileExists($swedish, "lang/sv/{$name}.php is missing.");

            $this->assertSame(
                $this->flatten(require $file->getPathname()),
                $this->flatten(require $swedish),
                "lang/en/{$name}.php and lang/sv/{$name}.php do not define the same keys."
            );
        }
    }

    /** @return array<int, string> */
    private function flatten(array $lines, string $prefix = ''): array
    {
        $keys = [];

        foreach ($lines as $key => $value) {
            $keys = array_merge(
                $keys,
                is_array($value) ? $this->flatten($value, "{$prefix}{$key}.") : ["{$prefix}{$key}"],
            );
        }

        sort($keys);

        return $keys;
    }

    /**
     * The whole path, as a browser walks it: the user's stored choice, through SetUserLocale,
     * into the rendered navigation. One request per test — Filament builds its navigation once
     * per app instance, and a browser gets a fresh one on every request.
     */
    public function test_a_real_request_renders_the_panel_in_swedish(): void
    {
        $user = User::factory()->create(['locale' => 'sv']);

        $this->actingAs($user)
            ->get('/app/recipes')
            ->assertOk()
            ->assertSee('Mina recept')
            ->assertSee('Bibliotek')
            ->assertSee('Inga recept än')
            ->assertDontSee('My recipes');
    }

    public function test_a_real_request_renders_the_panel_in_english(): void
    {
        $user = User::factory()->create(['locale' => 'en']);

        $this->actingAs($user)
            ->get('/app/recipes')
            ->assertOk()
            ->assertSee('My recipes')
            ->assertSee('Library')
            ->assertSee('No recipes yet')
            ->assertDontSee('Mina recept');
    }

    public function test_resource_labels_and_navigation_groups_follow_the_locale(): void
    {
        $this->assertSame('Recipes', RecipeResource::getNavigationLabel());
        $this->assertSame('My recipes', RecipeResource::getNavigationGroup());
        $this->assertSame('Library', IngredientResource::getNavigationGroup());

        $this->app->setLocale('sv');

        $this->assertSame('Recept', RecipeResource::getNavigationLabel());
        $this->assertSame('Mina recept', RecipeResource::getNavigationGroup());
        $this->assertSame('Bibliotek', IngredientResource::getNavigationGroup());
    }

    public function test_the_recipe_list_reads_in_swedish(): void
    {
        $user = $this->owner();

        $written = $this->recipeFor($user);
        $revision = $written->revisions()->create([
            'locale' => 'sv',
            'version_number' => 1,
            'status' => 'published',
            'title' => 'Pannkakor',
            'servings' => 4,
            'published_at' => now(),
            'created_by_user_id' => $user->id,
        ]);
        $flour = Ingredient::create(['canonical_name' => 'vetemjöl']);
        $revision->ingredients()->create(['ingredient_id' => $flour->id, 'quantity' => 3, 'sort_order' => 1]);
        $revision->instructionSteps()->create(['instruction_text' => 'Vispa ihop.', 'sort_order' => 1]);

        $link = $this->recipeFor($user, 'https://www.ica.se/recept/kladdkaka-724129/');
        $link->revisions()->create([
            'locale' => 'sv',
            'version_number' => 1,
            'status' => 'draft',
            'title' => 'Kladdkaka med havssalt',
            'created_by_user_id' => $user->id,
        ]);

        $this->app->setLocale('sv');

        Livewire::test(ListRecipes::class)
            ->assertOk()
            // Counts are pluralized through the Swedish lines, not English ones.
            ->assertSee('4 portioner · 1 ingrediens · 1 steg · inga foton')
            ->assertSee('Sparat från ica.se · inget nedskrivet')
            ->assertSee('Klart')
            ->assertSee('Testas ännu')
            ->assertDontSee('servings')
            ->assertDontSee('Still testing');
    }

    public function test_the_editor_reads_in_swedish(): void
    {
        $user = $this->owner();
        $recipe = $this->recipeFor($user, 'https://www.ica.se/recept/kladdkaka-724129/');
        $revision = $recipe->revisions()->create([
            'locale' => 'sv',
            'version_number' => 1,
            'status' => 'draft',
            'title' => 'Kladdkaka',
            'created_by_user_id' => $user->id,
        ]);

        $this->app->setLocale('sv');

        Livewire::test(EditRecipeRevision::class, ['record' => $revision->getKey()])
            ->assertOk()
            ->assertSee('Inget listat än')
            ->assertSee('Öppna originalet')
            ->assertSee('Ingredienser')
            ->assertDontSee('Nothing listed yet');
    }

    public function test_the_read_only_view_reads_in_swedish(): void
    {
        $user = $this->owner();
        $recipe = $this->recipeFor($user);
        $revision = $recipe->revisions()->create([
            'locale' => 'sv',
            'version_number' => 1,
            'status' => 'published',
            'title' => 'Pannkakor',
            'created_by_user_id' => $user->id,
            'published_at' => now(),
        ]);
        // The read-only view walks groups and sections, so the content needs both.
        $flour = Ingredient::create(['canonical_name' => 'vetemjöl']);
        $group = $revision->ingredientGroups()->create(['title' => null, 'sort_order' => 1]);
        $revision->ingredients()->create([
            'group_id' => $group->id,
            'ingredient_id' => $flour->id,
            'quantity' => 3,
            'optional' => true,
            'sort_order' => 1,
        ]);
        $section = $revision->instructionSections()->create(['title' => null, 'sort_order' => 1]);
        $revision->instructionSteps()->create([
            'section_id' => $section->id,
            'instruction_text' => 'Vispa ihop.',
            'sort_order' => 1,
        ]);

        $this->app->setLocale('sv');

        Livewire::test(ViewRecipeRevision::class, ['record' => $revision->getKey()])
            ->assertOk()
            ->assertSee('Det här behöver du')
            ->assertSee('Så gör du')
            ->assertSee('Steg 1')
            // The "(optional)" tail on an ingredient line is translated too.
            ->assertSee('3 vetemjöl (valfri)')
            ->assertSee('Klart')
            ->assertDontSee('What you need')
            ->assertDontSee('How to make it')
            ->assertDontSee('(optional)');
    }

    public function test_status_words_and_ingredient_lines_are_translated(): void
    {
        $this->assertSame('Ready', RecipeRevision::statusWord('published'));
        $this->assertSame(
            '1 tsk salt (optional)',
            RecipeRevisionIngredient::formatLine('1', 'tsk', 'salt', null, true),
        );

        $this->app->setLocale('sv');

        $this->assertSame('Klart', RecipeRevision::statusWord('published'));
        $this->assertSame(
            '1 tsk salt (valfri)',
            RecipeRevisionIngredient::formatLine('1', 'tsk', 'salt', null, true),
        );

        // A status with no wording of its own is shown raw, not as a missing key.
        $this->assertSame('mystery', RecipeRevision::statusWord('mystery'));
        $this->assertNull(RecipeRevision::statusWord(null));
    }

    public function test_a_new_recipes_seeded_title_is_written_in_the_recipes_locale(): void
    {
        $this->owner();

        // The reader is on English; the recipe's own locale is Swedish. The seeded title is
        // revision content, so it follows the recipe rather than the reader.
        $this->app->setLocale('en');

        Livewire::test(CreateRecipe::class)
            ->fillForm([
                'default_locale' => 'sv',
                'visibility' => 'private',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame('Namnlöst recept', Recipe::sole()->revisions()->sole()->title);
    }
}
