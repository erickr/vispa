<?php

namespace Tests\Feature;

use App\Filament\Resources\Recipes\Pages\EditRecipe;
use App\Filament\Resources\Recipes\RecipeResource;
use App\Jobs\ImportRecipe;
use App\Models\Recipe;
use App\Models\RecipeImport;
use App\Models\RecipeRevision;
use App\Models\User;
use App\Recipes\Import\ExtractedRecipe;
use App\Recipes\Import\RecipeExtractor;
use App\Recipes\Import\RecipeSource;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * A recipe that is only a saved link, given a new link on its settings page, offers to read the
 * page into itself — filling the gaps and leaving what the cook already wrote.
 */
class ImportIntoLinkOnlyRecipeTest extends TestCase
{
    use RefreshDatabase;

    private const OLD = 'http://93.184.215.14/old/';

    private const PAGE = 'http://93.184.215.14/recept/plankstek/';

    private User $user;

    private Recipe $recipe;

    private RecipeRevision $revision;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('app');
        config(['services.anthropic.key' => 'test-key']);
        Storage::fake('public');

        $this->user = User::factory()->create(['locale' => 'sv']);
        $this->recipe = Recipe::create([
            'owner_user_id' => $this->user->getKey(),
            'default_locale' => 'sv',
            'visibility' => 'private',
            'source_url' => self::OLD,
        ]);
        $this->revision = $this->recipe->revisions()->create([
            'locale' => 'sv', 'version_number' => 1, 'status' => 'draft',
            'title' => 'Mormors plankstek', 'created_by_user_id' => $this->user->getKey(),
        ]);
    }

    private function fakeImport(): void
    {
        Http::fake([
            '*.jpg' => Http::response('jpeg-bytes', 200, ['Content-Type' => 'image/jpeg']),
            '*' => Http::response(file_get_contents(base_path('tests/Fixtures/recipe-page.html')), 200, ['Content-Type' => 'text/html']),
        ]);

        $this->app->instance(RecipeExtractor::class, new class implements RecipeExtractor
        {
            public function extract(RecipeSource $source, array $unitCodes): ExtractedRecipe
            {
                return ExtractedRecipe::fromArray([
                    'is_recipe' => true, 'title' => 'Plankstek', 'description' => 'En klassiker.',
                    'locale' => 'sv', 'servings' => 4, 'prep_minutes' => 20, 'cook_minutes' => 40,
                    'total_minutes' => null, 'source_credit' => 'ICA',
                    'image_url' => 'http://93.184.215.14/plankstek.jpg',
                    'ingredient_groups' => [['title' => null, 'items' => [
                        ['raw' => '1 kg potatis', 'quantity' => 1, 'quantity_min' => null, 'quantity_max' => null, 'unit' => 'kg', 'ingredient' => 'potatis', 'preparation_note' => null, 'optional' => false],
                    ]]],
                    'instruction_sections' => [['title' => null, 'steps' => [
                        ['text' => 'Koka potatisen.', 'timer_seconds' => null],
                    ]]],
                ]);
            }
        });
    }

    private function editor()
    {
        $this->actingAs($this->user);

        return Livewire::test(EditRecipe::class, ['record' => $this->recipe->getKey()]);
    }

    public function test_the_checkbox_shows_once_a_new_link_is_entered(): void
    {
        $this->editor()
            ->assertFormFieldHidden('import_from_source')
            ->fillForm(['source_url' => self::PAGE])
            ->assertFormFieldVisible('import_from_source')
            ->fillForm(['source_url' => self::OLD])
            ->assertFormFieldHidden('import_from_source')
            ->fillForm(['source_url' => 'not a link'])
            ->assertFormFieldHidden('import_from_source');
    }

    public function test_the_checkbox_is_not_offered_once_the_recipe_is_written_out(): void
    {
        $this->revision->instructionSteps()->create(['instruction_text' => 'Värm ugnen.', 'sort_order' => 1]);

        $this->editor()
            ->fillForm(['source_url' => self::PAGE])
            ->assertFormFieldHidden('import_from_source');
    }

    public function test_the_checkbox_is_not_offered_without_an_api_key(): void
    {
        config(['services.anthropic.key' => null]);

        $this->editor()
            ->fillForm(['source_url' => self::PAGE])
            ->assertFormFieldHidden('import_from_source');
    }

    public function test_saving_with_the_box_ticked_imports_into_this_recipe(): void
    {
        Queue::fake();

        $this->editor()
            ->fillForm(['source_url' => self::PAGE, 'import_from_source' => true])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertRedirect(RecipeResource::getUrl('import', ['import' => RecipeImport::sole()]));

        $import = RecipeImport::sole();
        $this->assertSame(self::PAGE, $import->source_url);
        $this->assertSame($this->recipe->getKey(), $import->recipe_id);
        $this->assertSame($this->revision->getKey(), $import->recipe_revision_id);
        $this->assertSame(self::PAGE, $this->recipe->fresh()->source_url);
        Queue::assertPushed(ImportRecipe::class);
    }

    public function test_saving_with_the_box_unticked_only_changes_the_link(): void
    {
        Queue::fake();

        $this->editor()
            ->fillForm(['source_url' => self::PAGE, 'import_from_source' => false])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(0, RecipeImport::count());
        $this->assertSame(self::PAGE, $this->recipe->fresh()->source_url);
    }

    public function test_the_import_fills_the_gaps_and_keeps_what_was_written(): void
    {
        $this->fakeImport();
        $this->revision->update(['servings' => 6]);

        // The sync queue runs the job straight away.
        $this->editor()
            ->fillForm(['source_url' => self::PAGE, 'import_from_source' => true])
            ->call('save');

        $import = RecipeImport::sole();
        $this->assertSame(RecipeImport::STATUS_DONE, $import->status);
        $this->assertSame($this->revision->getKey(), $import->recipe_revision_id);

        // Into this recipe; no second one.
        $this->assertSame(1, Recipe::count());
        $revision = $this->revision->fresh();
        $this->assertSame('Mormors plankstek', $revision->title);
        $this->assertSame(6, $revision->servings);
        $this->assertSame('En klassiker.', $revision->description);
        $this->assertSame('ICA', $revision->source_credit);
        $this->assertSame(20, $revision->prep_time_minutes);
        $this->assertSame(['Koka potatisen.'], $revision->instructionSteps->pluck('instruction_text')->all());
        $this->assertSame(1, $revision->ingredients()->count());
        $this->assertNotNull($revision->coverImage());
    }

    public function test_a_placeholder_title_is_replaced(): void
    {
        $this->fakeImport();
        $this->revision->update(['title' => __('recipe.untitled', locale: 'sv')]);

        $this->editor()->fillForm(['source_url' => self::PAGE, 'import_from_source' => true])->call('save');

        $this->assertSame('Plankstek', $this->revision->fresh()->title);
    }

    public function test_a_published_link_is_filled_as_a_new_draft(): void
    {
        $this->fakeImport();
        $this->revision->update(['status' => 'published', 'published_at' => now()]);

        $this->editor()->fillForm(['source_url' => self::PAGE, 'import_from_source' => true])->call('save');

        // What people may be reading stays as it was; the page lands in v2.
        $this->assertTrue($this->revision->fresh()->isLinkOnly());
        $draft = $this->recipe->revisions()->where('version_number', 2)->sole();
        $this->assertSame('draft', $draft->status);
        $this->assertSame(RecipeImport::sole()->recipe_revision_id, $draft->getKey());
        $this->assertFalse($draft->isLinkOnly());
    }

    public function test_a_recipe_written_out_while_the_import_waited_is_left_alone(): void
    {
        Queue::fake();
        $this->editor()->fillForm(['source_url' => self::PAGE, 'import_from_source' => true])->call('save');
        $this->revision->instructionSteps()->create(['instruction_text' => 'Värm ugnen.', 'sort_order' => 1]);

        $this->fakeImport();
        // The queue is still faked from the save; run the job it held back by hand.
        app()->call([new ImportRecipe(RecipeImport::sole()), 'handle']);

        $import = RecipeImport::sole()->fresh();
        $this->assertSame(RecipeImport::STATUS_FAILED, $import->status);
        $this->assertSame(__('recipe.import.errors.not_empty', locale: 'sv'), $import->error);
        $this->assertSame(['Värm ugnen.'], $this->revision->instructionSteps()->pluck('instruction_text')->all());
    }
}
