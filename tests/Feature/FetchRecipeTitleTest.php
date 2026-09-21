<?php

namespace Tests\Feature;

use App\Filament\Resources\Recipes\Pages\CreateRecipe;
use App\Models\Recipe;
use App\Models\User;
use App\Support\PageTitleFetcher;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * "Hämta" next to the link reads the page's heading into the title, so a saved link has a name.
 */
class FetchRecipeTitleTest extends TestCase
{
    use RefreshDatabase;

    // A literal public IP, so the test needs no DNS.
    private const URL = 'http://93.184.215.14/recept/plankstek-728945/';

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('app');
        $this->actingAs(User::factory()->create(['locale' => 'sv']));
        $this->app->setLocale('sv');
    }

    private function fetchButton(): TestAction
    {
        return TestAction::make('fetchTitle')->schemaComponent('source_url');
    }

    public function test_the_fetched_heading_becomes_the_first_revisions_title(): void
    {
        Http::fake(['*' => Http::response(
            '<html><head><title>Plankstek | Recept ICA.se</title></head>'
            .'<body><h1 class="recipe-header__title"> Plankstek  med  äpple </h1></body></html>'
        )]);

        Livewire::test(CreateRecipe::class)
            ->assertSee('Hämta')
            ->fillForm(['source_url' => self::URL])
            ->callAction($this->fetchButton())
            ->assertSchemaStateSet(['title' => 'Plankstek med äpple'])
            ->call('create')
            ->assertHasNoFormErrors();

        $recipe = Recipe::sole();
        $this->assertSame(self::URL, $recipe->source_url);
        $this->assertSame('Plankstek med äpple', $recipe->revisions()->sole()->title);
    }

    public function test_a_page_without_a_heading_leaves_the_title_alone(): void
    {
        Http::fake(['*' => Http::response('', 404)]);

        Livewire::test(CreateRecipe::class)
            ->fillForm(['source_url' => self::URL, 'title' => 'Mitt namn'])
            ->callAction($this->fetchButton())
            ->assertNotified(__('recipe.notifications.fetch_title.not_found'))
            ->assertSchemaStateSet(['title' => 'Mitt namn']);
    }

    public function test_an_untitled_link_still_gets_the_placeholder_title(): void
    {
        Livewire::test(CreateRecipe::class)
            ->fillForm(['source_url' => self::URL])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame('Namnlöst recept', Recipe::sole()->revisions()->sole()->title);
    }

    public function test_private_and_local_addresses_are_never_fetched(): void
    {
        Http::fake();
        $fetcher = new PageTitleFetcher;

        foreach (['http://127.0.0.1/', 'http://localhost/', 'http://10.0.0.5/', 'http://169.254.169.254/latest/meta-data/', 'http://[::1]/', 'file:///etc/passwd'] as $url) {
            $this->assertNull($fetcher->fetch($url), $url);
        }

        Http::assertNothingSent();
    }

    public function test_falls_back_to_og_title_then_title(): void
    {
        $fetcher = new PageTitleFetcher;

        $this->assertSame('Kanelbullar', $fetcher->titleFromHtml('<meta property="og:title" content="Kanelbullar"><title>x</title>'));
        $this->assertSame('Pannkakor', $fetcher->titleFromHtml('<title>Pannkakor</title>'));
        $this->assertNull($fetcher->titleFromHtml('<p>nothing</p>'));
    }
}
