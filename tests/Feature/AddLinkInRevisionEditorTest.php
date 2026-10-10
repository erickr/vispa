<?php

namespace Tests\Feature;

use App\Actions\Households\CreatePersonalHousehold;
use App\Actions\Meals\PlanMeal;
use App\Filament\Resources\RecipeRevisions\Pages\EditRecipeRevision;
use App\Filament\Resources\Recipes\RecipeResource;
use App\Jobs\ImportRecipe;
use App\Models\Recipe;
use App\Models\RecipeImport;
use App\Models\RecipeRevision;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * A dish planned by name opens in the revision editor. Adding its link there offers to read the
 * page into it, the same as the recipe's settings page.
 */
class AddLinkInRevisionEditorTest extends TestCase
{
    use RefreshDatabase;

    private const PAGE = 'http://93.184.215.14/recept/plankstek/';

    private User $user;

    private Recipe $recipe;

    private RecipeRevision $revision;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('app');
        config(['services.anthropic.key' => 'test-key']);

        $this->user = User::factory()->create(['locale' => 'sv']);
        app(CreatePersonalHousehold::class)->handle($this->user);
        $this->revision = app(PlanMeal::class)->newDish($this->user, 'Plankstek')->recipe->revisions()->sole();
        $this->recipe = $this->revision->recipe;
    }

    private function editor()
    {
        $this->actingAs($this->user);

        return Livewire::test(EditRecipeRevision::class, ['record' => $this->revision->getKey()]);
    }

    public function test_a_dish_with_only_a_name_can_be_given_a_link(): void
    {
        $this->editor()->assertActionVisible('addLink');
    }

    public function test_a_recipe_that_has_a_link_is_not_offered_another(): void
    {
        $this->recipe->update(['source_url' => self::PAGE]);

        $this->editor()->assertActionHidden('addLink');
    }

    public function test_adding_the_link_imports_into_this_revision(): void
    {
        Queue::fake();

        $this->editor()
            ->callAction('addLink', data: ['source_url' => self::PAGE, 'import_from_source' => true])
            ->assertHasNoFormErrors()
            ->assertRedirect(RecipeResource::getUrl('import', ['import' => RecipeImport::sole()]));

        $import = RecipeImport::sole();
        $this->assertSame(self::PAGE, $import->source_url);
        $this->assertSame($this->recipe->getKey(), $import->recipe_id);
        $this->assertSame($this->revision->getKey(), $import->recipe_revision_id);
        $this->assertSame(self::PAGE, $this->recipe->fresh()->source_url);
        Queue::assertPushed(ImportRecipe::class);
    }

    public function test_what_was_typed_in_the_editor_is_kept(): void
    {
        Queue::fake();

        $this->editor()
            ->fillForm(['title' => 'Mormors plankstek'])
            ->callAction('addLink', data: ['source_url' => self::PAGE, 'import_from_source' => true]);

        $this->assertSame('Mormors plankstek', $this->revision->fresh()->title);
    }

    public function test_unticked_it_only_saves_the_link(): void
    {
        Queue::fake();

        $this->editor()
            ->callAction('addLink', data: ['source_url' => self::PAGE, 'import_from_source' => false])
            ->assertHasNoFormErrors()
            ->assertNoRedirect();

        $this->assertSame(0, RecipeImport::count());
        $this->assertSame(self::PAGE, $this->recipe->fresh()->source_url);
    }

    public function test_a_written_out_recipe_only_gets_the_link(): void
    {
        Queue::fake();
        $this->revision->instructionSteps()->create(['instruction_text' => 'Värm ugnen.', 'sort_order' => 1]);

        $this->editor()->callAction('addLink', data: ['source_url' => self::PAGE, 'import_from_source' => true]);

        $this->assertSame(0, RecipeImport::count());
        $this->assertSame(self::PAGE, $this->recipe->fresh()->source_url);
    }

    public function test_without_an_api_key_it_only_saves_the_link(): void
    {
        Queue::fake();
        config(['services.anthropic.key' => null]);

        $this->editor()->callAction('addLink', data: ['source_url' => self::PAGE, 'import_from_source' => true]);

        $this->assertSame(0, RecipeImport::count());
        $this->assertSame(self::PAGE, $this->recipe->fresh()->source_url);
    }

    public function test_only_web_links_are_taken(): void
    {
        $this->editor()
            ->callAction('addLink', data: ['source_url' => 'javascript:alert(1)'])
            ->assertHasActionErrors(['source_url']);

        $this->assertNull($this->recipe->fresh()->source_url);
    }
}
