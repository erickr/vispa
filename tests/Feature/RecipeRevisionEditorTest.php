<?php

namespace Tests\Feature;

use App\Filament\Resources\RecipeRevisions\Pages\EditRecipeRevision;
use App\Filament\Resources\RecipeRevisions\Pages\ViewRecipeRevision;
use App\Filament\Resources\RecipeRevisions\RecipeRevisionResource;
use App\Filament\Resources\Recipes\Pages\CreateRecipe;
use App\Filament\Resources\Recipes\Pages\ListRecipes;
use App\Models\Ingredient;
use App\Models\Recipe;
use App\Models\RecipeRevision;
use App\Models\Unit;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class RecipeRevisionEditorTest extends TestCase
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

    private function recipeFor(User $user): Recipe
    {
        return Recipe::create([
            'owner_user_id' => $user->id,
            'default_locale' => 'en',
            'visibility' => 'private',
        ]);
    }

    public function test_editor_persists_nested_content_with_revision_id_and_sort_order(): void
    {
        $user = $this->owner();
        $recipe = $this->recipeFor($user);

        $revision = $recipe->revisions()->create([
            'locale' => 'en',
            'version_number' => 1,
            'status' => 'draft',
            'title' => 'Pancakes',
            'created_by_user_id' => $user->id,
        ]);

        $flour = Ingredient::create(['canonical_name' => 'flour']);
        $milk = Ingredient::create(['canonical_name' => 'milk']);
        $gram = Unit::firstOrCreate(['code' => 'g'], ['type' => 'mass']);

        Livewire::test(EditRecipeRevision::class, ['record' => $revision->getKey()])
            ->fillForm([
                'ingredientGroups' => [
                    [
                        'title' => 'Batter',
                        'ingredients' => [
                            ['ingredient_id' => $flour->id, 'quantity' => '1 1/2', 'unit_id' => $gram->id, 'optional' => false, 'preparation_note' => 'sifted'],
                            ['ingredient_id' => $milk->id, 'quantity' => '2.5', 'unit_id' => null, 'optional' => true, 'preparation_note' => null],
                        ],
                    ],
                ],
                'instructionSections' => [
                    [
                        'title' => 'Cook',
                        'steps' => [
                            ['instruction_text' => 'Mix everything.', 'timer_seconds' => null],
                            ['instruction_text' => 'Fry until golden.', 'timer_seconds' => 120],
                        ],
                    ],
                ],
            ])
            ->call('save')
            ->assertHasNoErrors();

        $revision->refresh();

        $group = $revision->ingredientGroups()->firstOrFail();
        $this->assertSame('Batter', $group->title);
        $this->assertCount(2, $group->ingredients);

        $ingredients = $revision->ingredients()->orderBy('sort_order')->get();
        // Grandparent FK backfilled even though the nested relationship only sets group_id.
        $ingredients->each(fn ($i) => $this->assertSame($revision->id, $i->recipe_revision_id));
        $ingredients->each(fn ($i) => $this->assertSame($group->id, $i->group_id));
        $this->assertSame('1.500', $ingredients[0]->quantity); // fraction parsed
        $this->assertSame('2.500', $ingredients[1]->quantity);
        // Sort order is persisted and strictly ascending in entry order (Filament's orderColumn is 1-based).
        $order = $ingredients->pluck('sort_order')->all();
        $this->assertSame($order, array_values(array_unique($order)));
        $this->assertTrue($order[0] < $order[1]);

        $steps = $revision->instructionSteps()->orderBy('sort_order')->get();
        $this->assertCount(2, $steps);
        $steps->each(fn ($s) => $this->assertSame($revision->id, $s->recipe_revision_id));
        $this->assertSame(120, $steps[1]->timer_seconds);
    }

    public function test_creating_a_recipe_seeds_a_draft_and_lands_in_the_content_editor(): void
    {
        $user = $this->owner();

        $component = Livewire::test(CreateRecipe::class)
            ->fillForm([
                'default_locale' => 'sv',
                'visibility' => 'private',
            ])
            ->call('create')
            ->assertHasNoErrors();

        $recipe = Recipe::where('owner_user_id', $user->id)->firstOrFail();

        // A first draft revision is seeded in the recipe's default locale...
        $revision = $recipe->revisions()->firstOrFail();
        $this->assertSame('draft', $revision->status);
        $this->assertSame('sv', $revision->locale);
        $this->assertSame(1, $revision->version_number);

        // ...and the user is redirected to that revision's full content editor.
        $component->assertRedirect(RecipeRevisionResource::getUrl('edit', ['record' => $revision]));
    }

    public function test_view_page_renders_a_revisions_content(): void
    {
        $user = $this->owner();
        $recipe = $this->recipeFor($user);

        $revision = $recipe->revisions()->create([
            'locale' => 'en',
            'version_number' => 1,
            'status' => 'published',
            'title' => 'Pancakes',
            'created_by_user_id' => $user->id,
            'published_at' => now(),
        ]);
        $group = $revision->ingredientGroups()->create(['title' => 'Batter', 'sort_order' => 1]);
        $flour = Ingredient::create(['canonical_name' => 'flour']);
        $revision->ingredients()->create([
            'group_id' => $group->id,
            'ingredient_id' => $flour->id,
            'quantity' => 150,
            'sort_order' => 1,
        ]);
        $section = $revision->instructionSections()->create(['title' => 'Cook', 'sort_order' => 1]);
        $revision->instructionSteps()->create([
            'section_id' => $section->id,
            'instruction_text' => 'Mix and fry.',
            'sort_order' => 1,
        ]);

        Livewire::test(ViewRecipeRevision::class, ['record' => $revision->getKey()])
            ->assertOk()
            ->assertSee('Pancakes')
            ->assertSee('Batter')
            ->assertSee('flour')
            ->assertSee('Mix and fry.');
    }

    public function test_display_revision_prefers_the_newest_work_in_the_default_locale(): void
    {
        $user = $this->owner();
        $recipe = $this->recipeFor($user); // default_locale = 'en'

        $recipe->revisions()->create(['locale' => 'en', 'version_number' => 1, 'status' => 'published', 'title' => 'en v1', 'created_by_user_id' => $user->id, 'published_at' => now()]);
        $recipe->revisions()->create(['locale' => 'en', 'version_number' => 2, 'status' => 'draft', 'title' => 'en v2 draft', 'created_by_user_id' => $user->id]);
        $recipe->revisions()->create(['locale' => 'sv', 'version_number' => 3, 'status' => 'published', 'title' => 'sv v3', 'created_by_user_id' => $user->id, 'published_at' => now()]);

        // A draft started off the published version is what the cook is working on, so it leads;
        // the default locale (en) still wins over the higher-versioned Swedish revision.
        $this->assertSame('en v2 draft', $recipe->displayRevision()->title);

        // An archived revision only leads when there is nothing else.
        $recipe->revisions()->where('version_number', 2)->update(['status' => 'archived']);
        $this->assertSame('en v1', $recipe->fresh()->displayRevision()->title);
    }

    /**
     * @return array{0: Recipe, 1: RecipeRevision}
     */
    private function publishedRecipeWithContent(User $user): array
    {
        $recipe = $this->recipeFor($user);

        $published = $recipe->revisions()->create([
            'locale' => 'en',
            'version_number' => 1,
            'status' => 'published',
            'title' => 'Original',
            'created_by_user_id' => $user->id,
            'published_at' => now(),
        ]);
        $group = $published->ingredientGroups()->create(['title' => 'G1', 'sort_order' => 0]);
        $published->ingredients()->create([
            'group_id' => $group->id,
            'ingredient_id' => Ingredient::create(['canonical_name' => 'flour'])->id,
            'quantity' => 100,
            'sort_order' => 0,
        ]);

        return [$recipe, $published];
    }

    public function test_opening_a_published_revision_asks_before_creating_anything(): void
    {
        [$recipe, $published] = $this->publishedRecipeWithContent($this->owner());

        Livewire::test(EditRecipeRevision::class, ['record' => $published->getKey()])
            ->assertActionMounted('revisionChoice');

        // Nothing is written until the question is answered.
        $this->assertSame(1, $recipe->revisions()->count());
    }

    public function test_saying_yes_forks_the_published_revision_into_a_draft(): void
    {
        $user = $this->owner();
        [$recipe, $published] = $this->publishedRecipeWithContent($user);

        Livewire::test(EditRecipeRevision::class, ['record' => $published->getKey()])
            ->callMountedAction();

        // The published revision is untouched; a new draft fork exists with copied content.
        $this->assertSame('published', $published->fresh()->status);

        $draft = RecipeRevision::where('recipe_id', $recipe->id)->where('status', 'draft')->firstOrFail();
        $this->assertSame(2, $draft->version_number);
        $this->assertSame('Original', $draft->title);
        $this->assertCount(1, $draft->ingredients);
        // Cloned ingredient points at the cloned group, not the original.
        $this->assertSame($draft->ingredientGroups()->first()->id, $draft->ingredients->first()->group_id);
        $this->assertSame($draft->id, $draft->ingredients->first()->recipe_revision_id);
    }

    public function test_a_started_revision_leads_the_recipe_list(): void
    {
        $user = $this->owner();
        [$recipe, $published] = $this->publishedRecipeWithContent($user);

        Livewire::test(EditRecipeRevision::class, ['record' => $published->getKey()])
            ->callMountedAction();

        RecipeRevision::where('recipe_id', $recipe->id)->where('status', 'draft')->firstOrFail()
            ->update(['title' => 'Original, in progress']);

        Livewire::test(ListRecipes::class)
            ->assertSee('Original, in progress')
            ->assertSee(RecipeRevision::statusWord('draft'));

        // The public page is not dragged along: it keeps showing what was published.
        $this->assertSame('Original', $recipe->fresh()->sharedRevision()->title);
    }

    public function test_the_view_page_links_to_the_other_versions(): void
    {
        $user = $this->owner();
        [$recipe, $published] = $this->publishedRecipeWithContent($user);

        $draft = $recipe->revisions()->create([
            'locale' => 'en',
            'version_number' => 2,
            'status' => 'draft',
            'title' => 'Original, in progress',
            'created_by_user_id' => $user->id,
        ]);
        $swedish = $recipe->revisions()->create([
            'locale' => 'sv',
            'version_number' => 1,
            'status' => 'published',
            'title' => 'Originalet',
            'created_by_user_id' => $user->id,
            'published_at' => now(),
        ]);

        // Matched as whole hrefs: a revision's view URL is a prefix of its own edit URL.
        $href = fn (RecipeRevision $revision): string => 'href="'.RecipeRevisionResource::getUrl('view', ['record' => $revision]).'"';

        Livewire::test(ViewRecipeRevision::class, ['record' => $published->getKey()])
            ->assertSee($href($draft), false)
            ->assertSee($href($swedish), false)
            // The version being read is named but not linked — a link to this page goes nowhere.
            ->assertDontSee($href($published), false)
            ->assertSee(__('revision.infolist.you_are_here'));
    }

    public function test_saying_yes_reuses_an_open_draft_rather_than_stacking_another(): void
    {
        $user = $this->owner();
        [$recipe, $published] = $this->publishedRecipeWithContent($user);

        $existing = $recipe->revisions()->create([
            'locale' => 'en',
            'version_number' => 2,
            'status' => 'draft',
            'title' => 'Already going',
            'created_by_user_id' => $user->id,
        ]);

        Livewire::test(EditRecipeRevision::class, ['record' => $published->getKey()])
            ->callMountedAction()
            ->assertRedirect(RecipeRevisionResource::getUrl('edit', ['record' => $existing]));

        $this->assertSame(2, $recipe->revisions()->count());
    }

    public function test_saying_no_edits_the_published_revision_in_place(): void
    {
        $user = $this->owner();
        [$recipe, $published] = $this->publishedRecipeWithContent($user);

        Livewire::test(EditRecipeRevision::class, ['record' => $published->getKey()])
            ->mountAction('editInPlace')
            ->assertActionNotMounted()
            ->fillForm(['title' => 'Original, corrected'])
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('Original, corrected', $published->fresh()->title);
        $this->assertSame(1, $recipe->revisions()->count());
    }

    public function test_cancelling_leaves_the_revision_alone(): void
    {
        [$recipe, $published] = $this->publishedRecipeWithContent($this->owner());

        Livewire::test(EditRecipeRevision::class, ['record' => $published->getKey()])
            ->mountAction('leaveItAlone')
            ->assertRedirect(RecipeRevisionResource::getUrl('view', ['record' => $published]));

        $this->assertSame(1, $recipe->revisions()->count());
        $this->assertSame('Original', $published->fresh()->title);
    }
}
