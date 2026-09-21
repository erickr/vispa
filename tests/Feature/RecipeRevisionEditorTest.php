<?php

namespace Tests\Feature;

use App\Filament\Resources\RecipeRevisions\Pages\EditRecipeRevision;
use App\Filament\Resources\RecipeRevisions\Pages\ViewRecipeRevision;
use App\Filament\Resources\RecipeRevisions\RecipeRevisionResource;
use App\Filament\Resources\Recipes\Pages\CreateRecipe;
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

    public function test_display_revision_prefers_published_default_locale_latest(): void
    {
        $user = $this->owner();
        $recipe = $this->recipeFor($user); // default_locale = 'en'

        $recipe->revisions()->create(['locale' => 'en', 'version_number' => 1, 'status' => 'published', 'title' => 'en v1', 'created_by_user_id' => $user->id, 'published_at' => now()]);
        $recipe->revisions()->create(['locale' => 'en', 'version_number' => 2, 'status' => 'draft', 'title' => 'en v2 draft', 'created_by_user_id' => $user->id]);
        $recipe->revisions()->create(['locale' => 'sv', 'version_number' => 3, 'status' => 'published', 'title' => 'sv v3', 'created_by_user_id' => $user->id, 'published_at' => now()]);

        // Published beats the higher-versioned draft; among published, the default locale (en) wins.
        $this->assertSame('en v1', $recipe->displayRevision()->title);
    }

    public function test_editing_a_published_revision_redirects_to_a_draft_fork(): void
    {
        $user = $this->owner();
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
        $flour = Ingredient::create(['canonical_name' => 'flour']);
        $published->ingredients()->create([
            'group_id' => $group->id,
            'ingredient_id' => $flour->id,
            'quantity' => 100,
            'sort_order' => 0,
        ]);

        Livewire::test(EditRecipeRevision::class, ['record' => $published->getKey()]);

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
}
