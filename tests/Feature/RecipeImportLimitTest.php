<?php

namespace Tests\Feature;

use App\Filament\Resources\Recipes\Pages\EditRecipe;
use App\Filament\Resources\Recipes\Pages\ImportRecipeStatus;
use App\Filament\Resources\Recipes\Pages\ListRecipes;
use App\Jobs\ImportRecipe;
use App\Models\Recipe;
use App\Models\RecipeImport;
use App\Models\User;
use App\Recipes\Import\ImportLimitReached;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Every import is a paid model call and anyone can sign up, so each user has an hourly and a
 * daily budget. Over it, every way in says so and nothing is recorded or queued.
 */
class RecipeImportLimitTest extends TestCase
{
    use RefreshDatabase;

    // A literal public IP, so nothing needs DNS.
    private const PAGE = 'http://93.184.215.14/recept/plankstek/';

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('app');
        config([
            'services.anthropic.key' => 'test-key',
            'services.anthropic.imports_per_hour' => 2,
            'services.anthropic.imports_per_day' => 3,
        ]);
        Queue::fake();

        $this->user = User::factory()->create(['locale' => 'sv']);
        $this->actingAs($this->user);
        $this->app->setLocale('sv');
    }

    public function test_the_list_action_stops_at_the_hourly_limit(): void
    {
        foreach (range(1, 2) as $attempt) {
            Livewire::test(ListRecipes::class)
                ->callAction('importFromLink', ['url' => self::PAGE])
                ->assertRedirect();
        }

        Livewire::test(ListRecipes::class)
            ->callAction('importFromLink', ['url' => self::PAGE])
            ->assertNotified(__('recipe.import.limited.title'))
            ->assertNoRedirect();

        $this->assertSame(2, RecipeImport::count());
        Queue::assertPushed(ImportRecipe::class, 2);
    }

    public function test_the_hourly_limit_lifts_but_the_daily_one_holds(): void
    {
        ImportRecipe::start($this->user, self::PAGE);
        ImportRecipe::start($this->user, self::PAGE);

        $this->travel(61)->minutes();
        ImportRecipe::start($this->user, self::PAGE);

        $this->travel(61)->minutes();

        try {
            ImportRecipe::start($this->user, self::PAGE);
            $this->fail('The fourth import of the day should be refused.');
        } catch (ImportLimitReached $e) {
            $this->assertGreaterThan(3600, $e->availableIn);
        }

        $this->assertSame(3, RecipeImport::count());

        $this->travel(1)->day();
        ImportRecipe::start($this->user, self::PAGE);
        $this->assertSame(4, RecipeImport::count());
    }

    public function test_the_budget_is_per_user(): void
    {
        ImportRecipe::start($this->user, self::PAGE);
        ImportRecipe::start($this->user, self::PAGE);

        $other = User::factory()->create();
        ImportRecipe::start($other, self::PAGE);

        $this->assertSame(1, RecipeImport::where('user_id', $other->getKey())->count());
    }

    public function test_a_retry_over_the_limit_stays_on_the_status_page(): void
    {
        $failed = ImportRecipe::start($this->user, self::PAGE);
        $failed->update(['status' => RecipeImport::STATUS_FAILED, 'error' => 'Hittade inget recept på sidan.']);
        ImportRecipe::start($this->user, self::PAGE);

        Livewire::test(ImportRecipeStatus::class, ['import' => $failed])
            ->call('retry')
            ->assertNotified(__('recipe.import.limited.title'))
            ->assertNoRedirect();

        $this->assertSame(2, RecipeImport::count());
    }

    public function test_a_new_link_over_the_limit_is_saved_without_importing_or_forking(): void
    {
        $recipe = Recipe::create([
            'owner_user_id' => $this->user->getKey(),
            'default_locale' => 'sv',
            'visibility' => 'private',
            'source_url' => 'http://93.184.215.14/old/',
        ]);
        // Published, so an allowed import would first fork a draft to fill.
        $recipe->revisions()->create([
            'locale' => 'sv', 'version_number' => 1, 'status' => 'published',
            'title' => 'Mormors plankstek', 'created_by_user_id' => $this->user->getKey(),
        ]);
        ImportRecipe::start($this->user, self::PAGE);
        ImportRecipe::start($this->user, self::PAGE);

        Livewire::test(EditRecipe::class, ['record' => $recipe->getKey()])
            ->fillForm(['source_url' => self::PAGE, 'import_from_source' => true])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertNotified(__('recipe.import.limited.title'))
            ->assertNoRedirect();

        $this->assertSame(self::PAGE, $recipe->fresh()->source_url);
        $this->assertSame(1, $recipe->revisions()->count());
        $this->assertSame(0, RecipeImport::where('recipe_id', $recipe->getKey())->count());
    }
}
