<?php

namespace Tests\Feature;

use App\Filament\Resources\RecipeRevisions\Pages\EditRecipeRevision;
use App\Filament\Resources\RecipeRevisions\RecipeRevisionResource;
use App\Filament\Resources\Recipes\Pages\ImportRecipeStatus;
use App\Filament\Resources\Recipes\Pages\ListRecipes;
use App\Filament\Resources\Recipes\RecipeResource;
use App\Jobs\ImportRecipe;
use App\Models\Ingredient;
use App\Models\RecipeImport;
use App\Models\User;
use App\Recipes\Import\ExtractedRecipe;
use App\Recipes\Import\RecipeExtractor;
use App\Recipes\Import\RecipeImporter;
use App\Recipes\Import\RecipeImportException;
use App\Recipes\Import\RecipeSource;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * A link becomes a private recipe with a draft revision holding everything Claude read. The
 * extractor is faked here; ClaudeRecipeExtractorTest covers the API call itself.
 */
class RecipeImportTest extends TestCase
{
    use RefreshDatabase;

    // Literal public IPs, so nothing needs DNS.
    private const PAGE = 'http://93.184.215.14/recept/plankstek-728945/';

    private const PHOTO = 'http://93.184.215.14/plankstek.jpg';

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('app');
        config(['services.anthropic.key' => 'test-key']);
        Storage::fake('public');

        $this->user = User::factory()->create(['locale' => 'sv']);
    }

    private function plankstek(array $overrides = []): ExtractedRecipe
    {
        return ExtractedRecipe::fromArray($overrides + [
            'is_recipe' => true,
            'title' => 'Plankstek',
            'description' => 'En restaurangklassiker.',
            'locale' => 'sv',
            'servings' => 4,
            'prep_minutes' => null,
            'cook_minutes' => null,
            'total_minutes' => 90,
            'source_credit' => 'ICA',
            'image_url' => self::PHOTO,
            'ingredient_groups' => [
                ['title' => 'Potatismos', 'items' => [
                    ['raw' => '1 kg mjölig potatis', 'quantity' => 1, 'quantity_min' => null, 'quantity_max' => null, 'unit' => 'kg', 'ingredient' => 'potatis', 'preparation_note' => 'mjölig', 'optional' => false],
                    ['raw' => '1 krm vitpeppar', 'quantity' => 1, 'quantity_min' => null, 'quantity_max' => null, 'unit' => 'krm', 'ingredient' => 'vitpeppar', 'preparation_note' => null, 'optional' => false],
                ]],
                ['title' => null, 'items' => [
                    ['raw' => '4 skivor ryggbiff', 'quantity' => 4, 'quantity_min' => null, 'quantity_max' => null, 'unit' => 'skivor', 'ingredient' => 'ryggbiff', 'preparation_note' => 'à 150 g', 'optional' => false],
                    ['raw' => '2-3 l vatten', 'quantity' => null, 'quantity_min' => 2, 'quantity_max' => 3, 'unit' => 'l', 'ingredient' => 'Vatten', 'preparation_note' => null, 'optional' => false],
                    ['raw' => 'gärna färsk persilja', 'quantity' => null, 'quantity_min' => null, 'quantity_max' => null, 'unit' => null, 'ingredient' => 'persilja', 'preparation_note' => 'färsk', 'optional' => true],
                    ['raw' => '500 g fast potatis', 'quantity' => 500, 'quantity_min' => null, 'quantity_max' => null, 'unit' => 'G', 'ingredient' => 'Potatis', 'preparation_note' => null, 'optional' => false],
                ]],
            ],
            'instruction_sections' => [['title' => null, 'steps' => [
                ['text' => 'Skala och koka potatisen.', 'timer_seconds' => null],
                ['text' => 'Gratinera i 10-15 minuter.', 'timer_seconds' => 900],
            ]]],
        ]);
    }

    private function fakeExtractor(ExtractedRecipe|RecipeImportException $result): void
    {
        $this->app->instance(RecipeExtractor::class, new class($result) implements RecipeExtractor
        {
            public function __construct(private ExtractedRecipe|RecipeImportException $result) {}

            public function extract(RecipeSource $source, array $unitCodes): ExtractedRecipe
            {
                if ($this->result instanceof RecipeImportException) {
                    throw $this->result;
                }

                return $this->result;
            }
        });
    }

    private function fakeWeb(bool $photoWorks = true): void
    {
        Http::fake([
            self::PHOTO => $photoWorks
                ? Http::response('jpeg-bytes', 200, ['Content-Type' => 'image/jpeg'])
                : Http::response('', 404),
            '*' => Http::response(file_get_contents(base_path('tests/Fixtures/recipe-page.html')), 200, ['Content-Type' => 'text/html']),
        ]);
    }

    public function test_the_sidebar_offers_both_ways_to_start_a_recipe(): void
    {
        $this->actingAs($this->user);

        $items = collect(Filament::getCurrentOrDefaultPanel()->getNavigationItems())
            ->filter(fn ($item): bool => $item->isVisible())
            ->keyBy(fn ($item): string => $item->getLabel());

        $this->assertSame(
            RecipeResource::getUrl('create'),
            $items[__('navigation.items.new_recipe')]->getUrl(),
        );
        $this->assertSame(
            RecipeResource::getUrl('index').'?action=importFromLink',
            $items[__('recipe.import.action')]->getUrl(),
        );
    }

    public function test_the_import_item_is_hidden_without_an_api_key(): void
    {
        $this->actingAs($this->user);
        config(['services.anthropic.key' => null]);

        $labels = collect(Filament::getCurrentOrDefaultPanel()->getNavigationItems())
            ->filter(fn ($item): bool => $item->isVisible())
            ->map(fn ($item): string => $item->getLabel());

        $this->assertFalse($labels->contains(__('recipe.import.action')));
        $this->assertTrue($labels->contains(__('navigation.items.new_recipe')));
    }

    public function test_the_import_link_opens_the_import_modal(): void
    {
        $this->actingAs($this->user);

        Livewire::withQueryParams(['action' => 'importFromLink'])
            ->test(ListRecipes::class)
            ->call('mountAction', 'importFromLink')
            ->assertActionMounted('importFromLink');
    }

    public function test_the_importer_writes_a_private_draft_with_everything_it_read(): void
    {
        $this->fakeWeb();
        $shared = Ingredient::create(['canonical_name' => 'potatis']);

        $revision = app(RecipeImporter::class)->import($this->plankstek(), $this->user, self::PAGE);
        $recipe = $revision->recipe;

        $this->assertSame($this->user->id, $recipe->owner_user_id);
        $this->assertSame('private', $recipe->visibility);
        $this->assertSame(self::PAGE, $recipe->source_url);
        $this->assertSame('sv', $recipe->default_locale);

        $this->assertSame('draft', $revision->status);
        $this->assertSame('Plankstek', $revision->title);
        $this->assertSame(4, $revision->servings);
        $this->assertSame('ICA', $revision->source_credit);
        $this->assertSame('Total tid: 90 min.', $revision->notes);

        $this->assertSame(['Potatismos', null], $revision->ingredientGroups->pluck('title')->all());

        $lines = $revision->ingredients()->with(['ingredient', 'unit'])->get();
        $this->assertSame(range(1, 6), $lines->pluck('sort_order')->all());
        $this->assertSame(['kg', 'krm', null, 'liter', null, 'g'], $lines->map(fn ($line) => $line->unit?->code)->all());

        // "potatis" and "Potatis" both land on the shared catalog row, not a private copy.
        $this->assertSame($shared->id, $lines[0]->ingredient_id);
        $this->assertSame($shared->id, $lines[5]->ingredient_id);

        // An unknown unit is kept in the note rather than lost.
        $this->assertSame('skivor, à 150 g', $lines[2]->preparation_note);
        $this->assertEquals([2, 3], [(float) $lines[3]->quantity_min, (float) $lines[3]->quantity_max]);
        $this->assertTrue((bool) $lines[4]->optional);

        // Anything not in the catalog becomes the importer's own private ingredient.
        $this->assertSame($this->user->id, Ingredient::where('canonical_name', 'ryggbiff')->sole()->owner_user_id);

        $this->assertSame(['Skala och koka potatisen.', 'Gratinera i 10-15 minuter.'], $revision->instructionSteps->pluck('instruction_text')->all());
        $this->assertSame(900, $revision->instructionSteps[1]->timer_seconds);

        // The editor only shows rows inside a group or section, so even untitled ones get one.
        $this->assertSame(0, $revision->ingredients()->whereNull('group_id')->count());
        $this->assertSame(0, $revision->instructionSteps()->whereNull('section_id')->count());
        $this->assertSame([null], $revision->instructionSections->pluck('title')->all());

        $cover = $revision->coverImage();
        $this->assertNotNull($cover);
        Storage::disk('public')->assertExists($cover->path);
    }

    public function test_a_broken_photo_does_not_stop_the_import(): void
    {
        $this->fakeWeb(photoWorks: false);

        $revision = app(RecipeImporter::class)->import($this->plankstek(), $this->user, self::PAGE);

        $this->assertSame('Plankstek', $revision->title);
        $this->assertCount(0, $revision->images);
    }

    public function test_the_catalog_admins_imports_add_shared_ingredients(): void
    {
        $this->fakeWeb();
        $admin = User::factory()->create(['email' => User::CATALOG_ADMIN_EMAIL]);

        app(RecipeImporter::class)->import($this->plankstek(), $admin, self::PAGE);

        $this->assertTrue(Ingredient::where('canonical_name', 'ryggbiff')->sole()->isShared());
    }

    public function test_the_list_action_queues_an_import_and_opens_its_status_page(): void
    {
        Queue::fake();
        $this->actingAs($this->user);

        Livewire::test(ListRecipes::class)
            ->callAction('importFromLink', ['url' => self::PAGE])
            ->assertHasNoActionErrors()
            ->assertRedirect(RecipeResource::getUrl('import', ['import' => RecipeImport::sole()]));

        $import = RecipeImport::sole();
        $this->assertSame(RecipeImport::STATUS_PENDING, $import->status);
        $this->assertSame(self::PAGE, $import->source_url);
        Queue::assertPushed(ImportRecipe::class, fn (ImportRecipe $job) => $job->import->is($import));
    }

    public function test_the_action_is_hidden_without_an_api_key(): void
    {
        config(['services.anthropic.key' => null]);
        $this->actingAs($this->user);

        Livewire::test(ListRecipes::class)->assertActionHidden('importFromLink');
    }

    public function test_the_job_imports_and_the_status_page_opens_the_draft(): void
    {
        $this->fakeWeb();
        $this->fakeExtractor($this->plankstek());
        $this->actingAs($this->user);

        // The sync queue runs the job straight away.
        $import = ImportRecipe::start($this->user, self::PAGE)->fresh();

        $this->assertSame(RecipeImport::STATUS_DONE, $import->status);
        $this->assertNotNull($import->recipe_revision_id);

        Livewire::test(ImportRecipeStatus::class, ['import' => $import])
            ->assertRedirect(RecipeRevisionResource::getUrl('edit', ['record' => $import->recipe_revision_id]));

        // What the user lands on: the editor's form holds the imported ingredients and steps.
        $data = Livewire::test(EditRecipeRevision::class, ['record' => $import->recipe_revision_id])->get('data');
        $groups = array_values($data['ingredientGroups']);
        $sections = array_values($data['instructionSections']);

        $this->assertSame(['Potatismos', null], array_column($groups, 'title'));
        $this->assertCount(6, array_merge(...array_map(fn ($group) => array_values($group['ingredients']), $groups)));
        $this->assertSame(
            ['Skala och koka potatisen.', 'Gratinera i 10-15 minuter.'],
            array_column(array_values($sections[0]['steps']), 'instruction_text'),
        );
    }

    public function test_a_failed_import_shows_why_in_the_users_language_and_can_be_retried(): void
    {
        $this->fakeWeb();
        $this->fakeExtractor(new RecipeImportException('Hittade inget recept på sidan.'));
        $this->actingAs($this->user);

        $import = ImportRecipe::start($this->user, self::PAGE)->fresh();

        $this->assertSame(RecipeImport::STATUS_FAILED, $import->status);

        Livewire::test(ImportRecipeStatus::class, ['import' => $import])
            ->assertSee('Hittade inget recept på sidan.')
            ->assertSee('Försök igen')
            ->call('retry')
            ->assertRedirect();

        $this->assertSame(2, RecipeImport::count());
    }

    public function test_an_unreachable_page_fails_cleanly(): void
    {
        Http::fake(['*' => Http::response('', 500)]);
        $this->fakeExtractor($this->plankstek());

        $import = ImportRecipe::start($this->user, self::PAGE)->fresh();

        $this->assertSame(RecipeImport::STATUS_FAILED, $import->status);
        $this->assertSame(__('recipe.import.errors.unreachable', locale: 'sv'), $import->error);
    }

    public function test_someone_elses_import_is_forbidden(): void
    {
        Queue::fake();
        $import = ImportRecipe::start($this->user, self::PAGE);

        $this->actingAs(User::factory()->create());
        $this->get(RecipeResource::getUrl('import', ['import' => $import]))->assertForbidden();
    }
}
