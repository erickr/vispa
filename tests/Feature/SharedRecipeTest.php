<?php

namespace Tests\Feature;

use App\Filament\Resources\Recipes\Pages\ListRecipes;
use App\Models\Ingredient;
use App\Models\Recipe;
use App\Models\RecipeRevision;
use App\Models\Unit;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

class SharedRecipeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('app');
    }

    private function recipe(string $visibility = 'public', array $attributes = []): Recipe
    {
        return Recipe::create(array_merge([
            'owner_user_id' => User::factory()->create()->id,
            'default_locale' => 'en',
            'visibility' => $visibility,
        ], $attributes));
    }

    private function revision(Recipe $recipe, array $attributes = []): RecipeRevision
    {
        return $recipe->revisions()->create(array_merge([
            'locale' => 'en',
            'version_number' => 1,
            'status' => 'published',
            'title' => 'Kladdkaka',
            'created_by_user_id' => $recipe->owner_user_id,
        ], $attributes));
    }

    public function test_a_public_recipe_is_readable_by_a_guest(): void
    {
        $recipe = $this->recipe('public', ['source_url' => 'https://www.ica.se/recept/kladdkaka/']);
        $revision = $this->revision($recipe, [
            'description' => 'Sticky in the middle.',
            'servings' => 8,
            'notes' => 'Better the next day.',
        ]);

        $group = $revision->ingredientGroups()->create(['title' => 'For the batter', 'sort_order' => 1]);
        $revision->ingredients()->create([
            'group_id' => $group->id,
            'ingredient_id' => Ingredient::firstOrCreate(['canonical_name' => 'butter'])->id,
            'unit_id' => Unit::firstOrCreate(['code' => 'g'])->id,
            'quantity' => 150,
            'sort_order' => 1,
        ]);
        $section = $revision->instructionSections()->create(['title' => 'Baking', 'sort_order' => 1]);
        $revision->instructionSteps()->create([
            'section_id' => $section->id,
            'instruction_text' => 'Melt the butter.',
            'sort_order' => 1,
        ]);

        $this->get($recipe->shareUrl())
            ->assertOk()
            ->assertSee('Kladdkaka')
            ->assertSee('Sticky in the middle.')
            ->assertSee('8 servings')
            ->assertSee('For the batter')
            ->assertSee('150 g butter')
            ->assertSee('Baking')
            ->assertSee('Melt the butter.')
            ->assertSee('Better the next day.')
            ->assertSee('ica.se');
    }

    public function test_an_unlisted_recipe_is_readable_but_asks_not_to_be_indexed(): void
    {
        $recipe = $this->recipe('unlisted');
        $this->revision($recipe);

        $this->get($recipe->shareUrl())
            ->assertOk()
            ->assertSee('name="robots" content="noindex"', false);
    }

    public function test_a_private_recipe_has_no_public_page(): void
    {
        $recipe = $this->recipe('private');
        $this->revision($recipe);

        $this->get($recipe->shareUrl())->assertNotFound();
    }

    public function test_a_recipe_without_any_revision_has_no_public_page(): void
    {
        $this->get($this->recipe('public')->shareUrl())->assertNotFound();
    }

    public function test_the_page_is_read_in_the_visitors_language_when_that_version_exists(): void
    {
        $recipe = $this->recipe('public', ['default_locale' => 'en']);
        $this->revision($recipe, ['title' => 'Sticky chocolate cake']);
        $this->revision($recipe, ['locale' => 'sv', 'title' => 'Kladdkaka']);

        $this->withHeader('Accept-Language', 'sv-SE,sv;q=0.9')
            ->get($recipe->shareUrl())
            ->assertSee('Kladdkaka')
            ->assertSee('<html lang="sv">', false);

        // Only English exists for this one, so a Swedish reader still gets something to read.
        $english = $this->recipe('public');
        $this->revision($english, ['title' => 'Sticky chocolate cake']);

        $this->withHeader('Accept-Language', 'sv-SE,sv;q=0.9')
            ->get($english->shareUrl())
            ->assertSee('Sticky chocolate cake')
            ->assertSee('<html lang="en">', false);
    }

    public function test_the_language_toggle_picks_the_other_version(): void
    {
        $recipe = $this->recipe('public');
        $this->revision($recipe, ['title' => 'Sticky chocolate cake']);
        $this->revision($recipe, ['locale' => 'sv', 'title' => 'Kladdkaka']);

        $this->get($recipe->shareUrl().'?lang=sv')->assertSee('Kladdkaka');
    }

    public function test_a_draft_stands_in_while_nothing_is_published(): void
    {
        $recipe = $this->recipe('unlisted');
        $this->revision($recipe, ['status' => 'draft', 'title' => 'Work in progress']);

        $this->get($recipe->shareUrl())
            ->assertOk()
            ->assertSee('Work in progress')
            ->assertSee(RecipeRevision::statusWord('draft'));
    }

    public function test_sharing_a_public_recipe_shows_its_link(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $recipe = $this->recipe('unlisted', ['owner_user_id' => $user->id]);
        $this->revision($recipe);

        $component = Livewire::test(ListRecipes::class)
            ->mountAction(TestAction::make('share')->table($recipe))
            ->assertActionMounted(TestAction::make('share')->table($recipe));

        $this->assertStringContainsString($recipe->shareUrl(), $this->modalContent($component));
    }

    public function test_sharing_a_private_recipe_offers_to_make_it_unlisted_first(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $recipe = $this->recipe('private', ['owner_user_id' => $user->id]);
        $this->revision($recipe);

        $component = Livewire::test(ListRecipes::class)
            ->mountAction(TestAction::make('share')->table($recipe));

        // The link is withheld until the owner agrees to the change: the modal explains instead.
        $this->assertSame(
            __('recipe.share.private_heading'),
            $component->instance()->getMountedAction()->getModalHeading(),
        );
        $this->assertStringNotContainsString($recipe->shareUrl(), $this->modalContent($component));

        $component->callMountedAction();

        $this->assertSame('unlisted', $recipe->fresh()->visibility);

        // The same modal stays open, now showing the link it was withholding.
        $this->assertSame(
            'share',
            $component->instance()->getMountedAction()->getName(),
        );
        $this->assertSame(
            __('recipe.share.heading'),
            $component->instance()->getMountedAction()->getModalHeading(),
        );
        $this->assertStringContainsString($recipe->shareUrl(), $this->modalContent($component));

        $this->get($recipe->shareUrl())->assertOk();
    }

    /**
     * What the open modal is showing, rendered. Filament renders modal bodies on the client, so
     * the component's own HTML does not carry it.
     */
    private function modalContent(Testable $component): string
    {
        $content = $component->instance()->getMountedAction()?->getModalContent();

        return $content instanceof View ? $content->render() : (string) $content;
    }
}
