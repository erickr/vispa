<?php

namespace Tests\Feature;

use App\Filament\Resources\RecipeRevisions\Pages\EditRecipeRevision;
use App\Filament\Resources\RecipeRevisions\Pages\ViewRecipeRevision;
use App\Filament\Resources\RecipeRevisions\Schemas\RecipeRevisionForm;
use App\Filament\Resources\Recipes\Pages\EditRecipe;
use App\Filament\Resources\Recipes\Pages\ListRecipes;
use App\Models\Ingredient;
use App\Models\Recipe;
use App\Models\RecipeRevision;
use App\Models\Unit;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class RecipeSourceAndPhotosTest extends TestCase
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

    private function revisionFor(Recipe $recipe, User $user, array $attributes = []): RecipeRevision
    {
        return $recipe->revisions()->create(array_merge([
            'locale' => 'en',
            'version_number' => 1,
            'status' => 'draft',
            'title' => 'Kladdkaka',
            'created_by_user_id' => $user->id,
        ], $attributes));
    }

    public function test_a_recipe_can_be_nothing_but_a_saved_link(): void
    {
        $user = $this->owner();
        $recipe = $this->recipeFor($user, 'https://www.ica.se/recept/kladdkaka-724129/');
        $revision = $this->revisionFor($recipe, $user);

        // No ingredients, no steps — the editor leads with the link, saves happily, and the
        // revision knows what it is.
        Livewire::test(EditRecipeRevision::class, ['record' => $revision->getKey()])
            ->assertSee('ica.se')
            ->assertSee('Nothing listed yet')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertTrue($revision->fresh()->isLinkOnly());
        $this->assertSame('ica.se', $recipe->fresh()->sourceHost());
    }

    public function test_the_source_link_is_saved_on_the_recipe_and_the_credit_on_the_revision(): void
    {
        $user = $this->owner();
        $recipe = $this->recipeFor($user);
        $revision = $this->revisionFor($recipe, $user, ['title' => 'Kanelbullar']);

        Livewire::test(EditRecipe::class, ['record' => $recipe->getKey()])
            ->fillForm(['source_url' => 'https://www.koket.se/kanelbullar'])
            ->call('save')
            ->assertHasNoErrors();

        Livewire::test(EditRecipeRevision::class, ['record' => $revision->getKey()])
            ->fillForm(['source_credit' => "Mormor Ingrid's notebook"])
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('https://www.koket.se/kanelbullar', $recipe->fresh()->source_url);
        $this->assertSame('koket.se', $recipe->fresh()->sourceHost());
        $this->assertSame("Mormor Ingrid's notebook", $revision->fresh()->source_credit);
    }

    public function test_only_one_photo_can_be_the_cover(): void
    {
        $user = $this->owner();
        $recipe = $this->recipeFor($user);
        $revision = $this->revisionFor($recipe, $user, ['title' => 'Kanelbullar']);

        $revision->images()->create(['path' => 'recipe-images/rising.jpg', 'alt_text' => 'Dough rising', 'is_cover' => true, 'sort_order' => 1]);
        $revision->images()->create(['path' => 'recipe-images/baked.jpg', 'alt_text' => 'Out of the oven', 'is_cover' => true, 'sort_order' => 2]);

        // Flagging a second cover demotes the first, so exactly one photo leads.
        $images = $revision->fresh()->images;
        $this->assertCount(2, $images);
        $this->assertSame(1, $images->where('is_cover', true)->count());
        $this->assertSame('Out of the oven', $revision->coverImage()->alt_text);

        // Sort order drives the gallery, and the cover need not be first.
        $this->assertSame(['recipe-images/rising.jpg', 'recipe-images/baked.jpg'], $images->pluck('path')->all());
    }

    public function test_a_photo_uploads_through_the_editor(): void
    {
        Storage::fake('public');

        $user = $this->owner();
        $recipe = $this->recipeFor($user);
        $revision = $this->revisionFor($recipe, $user, ['title' => 'Kanelbullar']);

        Livewire::test(EditRecipeRevision::class, ['record' => $revision->getKey()])
            ->fillForm([
                'images' => [
                    ['path' => [UploadedFile::fake()->image('rising.jpg')], 'alt_text' => 'Dough rising', 'is_cover' => true],
                ],
            ])
            ->call('save')
            ->assertHasNoErrors();

        $image = $revision->fresh()->images()->firstOrFail();
        $this->assertSame('Dough rising', $image->alt_text);
        $this->assertTrue($image->is_cover);
        Storage::disk('public')->assertExists($image->path);
    }

    public function test_a_draft_fork_carries_the_credit_and_the_photos(): void
    {
        $user = $this->owner();
        $recipe = $this->recipeFor($user, 'https://www.arla.se/recept/semlor/');

        $published = $this->revisionFor($recipe, $user, [
            'title' => 'Semlor',
            'status' => 'published',
            'source_credit' => 'Arla',
            'published_at' => now(),
        ]);
        $published->images()->create(['path' => 'recipe-images/semlor.jpg', 'alt_text' => 'Semlor', 'is_cover' => true, 'sort_order' => 1]);

        $draft = $published->draftFork($user->id);

        $this->assertSame('Arla', $draft->source_credit);
        $this->assertCount(1, $draft->images);
        // Same stored file, its own row — deleting one revision never orphans the other's photo.
        $this->assertSame('recipe-images/semlor.jpg', $draft->images->first()->path);
        $this->assertTrue($draft->images->first()->is_cover);
        $this->assertTrue($published->fresh()->images->first()->is_cover);
    }

    public function test_ingredient_lines_read_as_prose_when_collapsed(): void
    {
        $this->owner();

        $butter = Ingredient::create(['canonical_name' => 'butter']);
        $gram = Unit::create(['code' => 'g', 'type' => 'mass']);

        $this->assertSame('150 g butter, softened', RecipeRevisionForm::ingredientLine([
            'ingredient_id' => $butter->id,
            'unit_id' => $gram->id,
            'quantity' => '150.000',
            'preparation_note' => 'softened',
            'optional' => false,
        ]));

        // Fractions survive the round trip, and an amount-less line still reads.
        $this->assertSame('1.5 g butter (optional)', RecipeRevisionForm::ingredientLine([
            'ingredient_id' => $butter->id,
            'unit_id' => $gram->id,
            'quantity' => RecipeRevisionForm::parseQuantity('1 1/2'),
            'optional' => true,
        ]));

        $this->assertSame('butter', RecipeRevisionForm::ingredientLine(['ingredient_id' => $butter->id]));
        $this->assertSame('New ingredient', RecipeRevisionForm::ingredientLine([]));
    }

    public function test_display_revision_agrees_whether_or_not_revisions_are_eager_loaded(): void
    {
        $user = $this->owner();
        $recipe = $this->recipeFor($user); // default_locale = 'en'

        $recipe->revisions()->create(['locale' => 'en', 'version_number' => 1, 'status' => 'published', 'title' => 'en v1', 'created_by_user_id' => $user->id, 'published_at' => now()]);
        $recipe->revisions()->create(['locale' => 'en', 'version_number' => 2, 'status' => 'draft', 'title' => 'en v2 draft', 'created_by_user_id' => $user->id]);
        $recipe->revisions()->create(['locale' => 'sv', 'version_number' => 3, 'status' => 'published', 'title' => 'sv v3', 'created_by_user_id' => $user->id, 'published_at' => now()]);

        // The list renders a title, a status and a cover per row, so it eager-loads and the sort
        // happens in PHP. That path must pick the same revision as the SQL one.
        $this->assertSame('en v1', $recipe->fresh()->displayRevision()->title);
        $this->assertSame('en v1', Recipe::with('revisions')->find($recipe->id)->displayRevision()->title);
    }

    public function test_the_list_shows_the_recipe_name_and_what_is_written_down(): void
    {
        $user = $this->owner();

        $written = $this->recipeFor($user);
        $revision = $this->revisionFor($written, $user, ['title' => 'Pannkakor', 'status' => 'published', 'servings' => 4, 'published_at' => now()]);
        $flour = Ingredient::create(['canonical_name' => 'vetemjöl']);
        $revision->ingredients()->create(['ingredient_id' => $flour->id, 'quantity' => 3, 'sort_order' => 1]);
        $revision->instructionSteps()->create(['instruction_text' => 'Vispa ihop.', 'sort_order' => 1]);

        $link = $this->recipeFor($user, 'https://www.ica.se/recept/kladdkaka-724129/');
        $this->revisionFor($link, $user, ['title' => 'Kladdkaka med havssalt']);

        Livewire::test(ListRecipes::class)
            ->assertOk()
            // The name comes from the display revision — recipes themselves hold no title.
            ->assertSee('Pannkakor')
            ->assertSee('Kladdkaka med havssalt')
            ->assertSee('4 servings · 1 ingredient · 1 step · no photos')
            // A saved link says what it is instead of reporting three zeroes.
            ->assertSee('Saved from ica.se · nothing written down')
            ->assertSee('Ready')
            ->assertSee('Still testing');
    }

    public function test_the_list_can_be_searched_by_a_title_held_on_the_revision(): void
    {
        $user = $this->owner();

        $wanted = $this->recipeFor($user);
        $this->revisionFor($wanted, $user, ['title' => 'Pannkakor']);

        $other = $this->recipeFor($user);
        $this->revisionFor($other, $user, ['title' => 'Kanelbullar']);

        Livewire::test(ListRecipes::class)
            ->searchTable('Pannk')
            ->assertCanSeeTableRecords([$wanted])
            ->assertCanNotSeeTableRecords([$other]);
    }

    public function test_an_ingredient_reads_as_one_line_in_the_read_only_view(): void
    {
        $user = $this->owner();
        $recipe = $this->recipeFor($user);
        $revision = $this->revisionFor($recipe, $user, ['title' => 'Kanelbullar']);

        $butter = Ingredient::create(['canonical_name' => 'butter']);
        $gram = Unit::create(['code' => 'g', 'type' => 'mass']);
        $ingredient = $revision->ingredients()->create([
            'ingredient_id' => $butter->id,
            'unit_id' => $gram->id,
            'quantity' => '150',
            'preparation_note' => 'softened',
            'sort_order' => 1,
        ]);

        $this->assertSame('150 g butter, softened', $ingredient->fresh()->line());

        // Same wording as the editor's collapsed repeater label — one formatter serves both.
        $this->assertSame($ingredient->fresh()->line(), RecipeRevisionForm::ingredientLine([
            'ingredient_id' => $butter->id,
            'unit_id' => $gram->id,
            'quantity' => '150.000',
            'preparation_note' => 'softened',
        ]));
    }

    public function test_the_view_page_shows_where_the_recipe_came_from(): void
    {
        $user = $this->owner();
        $recipe = $this->recipeFor($user, 'https://www.ica.se/recept/kladdkaka-724129/');
        $revision = $this->revisionFor($recipe, $user, [
            'status' => 'published',
            'source_credit' => 'ICA',
            'published_at' => now(),
        ]);

        Livewire::test(ViewRecipeRevision::class, ['record' => $revision->getKey()])
            ->assertOk()
            ->assertSee('ica.se')
            ->assertSee('ICA');
    }
}
