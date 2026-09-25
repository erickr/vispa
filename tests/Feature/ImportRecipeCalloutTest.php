<?php

namespace Tests\Feature;

use App\Filament\Widgets\ImportRecipeCallout;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ImportRecipeCalloutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('app');
    }

    public function test_it_invites_an_import_and_says_why(): void
    {
        config(['services.anthropic.key' => 'test-key']);
        $this->actingAs(User::factory()->create());

        $this->assertTrue(ImportRecipeCallout::canView());

        Livewire::test(ImportRecipeCallout::class)
            ->assertSee(__('recipe.import.callout.heading'))
            ->assertSee(__('recipe.import.callout.read_title'))
            ->assertSee(__('recipe.import.callout.find_title'))
            ->assertSee(__('recipe.import.callout.notes_title'))
            // Opens the recipe list with the import modal already mounted.
            ->assertSee('/app/recipes?action=importFromLink', escape: false);
    }

    public function test_it_is_on_the_dashboard(): void
    {
        config(['services.anthropic.key' => 'test-key']);

        $this->actingAs(User::factory()->create())
            ->get(Filament::getUrl())
            ->assertOk()
            ->assertSeeLivewire(ImportRecipeCallout::class);
    }

    public function test_it_is_hidden_when_importing_is_not_configured(): void
    {
        config(['services.anthropic.key' => null]);

        $this->assertFalse(ImportRecipeCallout::canView());

        $this->actingAs(User::factory()->create())
            ->get(Filament::getUrl())
            ->assertOk()
            ->assertDontSeeLivewire(ImportRecipeCallout::class);
    }

    public function test_it_reads_in_swedish(): void
    {
        config(['services.anthropic.key' => 'test-key']);
        $this->actingAs(User::factory()->create(['locale' => 'sv']));
        app()->setLocale('sv');

        Livewire::test(ImportRecipeCallout::class)
            ->assertSee('Lättare att läsa')
            ->assertSee('Importera från länk');
    }
}
