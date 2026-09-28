<?php

namespace Tests\Feature;

use App\Http\Middleware\SetGuestLocale;
use App\Models\User;
use App\Support\PublicLocale;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Tests\TestCase;

/**
 * The panel's login and registration pages speak the language the visitor reads the public pages
 * in, and carry the same toggle.
 */
class GuestPanelLocaleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('app');
    }

    public function test_the_login_page_follows_the_landing_page_pick(): void
    {
        $this->get(route('landing', ['lang' => 'sv']))->assertOk();

        $this->get(route('filament.app.auth.login'))
            ->assertOk()
            ->assertSee('Kom ihåg mig');
    }

    public function test_the_registration_page_follows_the_browser_without_a_pick(): void
    {
        $this->withHeader('Accept-Language', 'sv-SE,sv;q=0.9,en;q=0.5')
            ->get(route('filament.app.auth.register'))
            ->assertOk()
            ->assertSee('Skapa konto')
            ->assertSee('Språk');
    }

    public function test_english_stays_the_default(): void
    {
        $this->get(route('filament.app.auth.login'))
            ->assertOk()
            ->assertSee('Remember me');
    }

    public function test_the_toggle_switches_the_page_and_is_remembered(): void
    {
        $this->get(route('filament.app.auth.register'))
            ->assertSee(route('filament.app.auth.register', ['lang' => 'sv']), false);

        $this->get(route('filament.app.auth.register', ['lang' => 'sv']))
            ->assertOk()
            ->assertSee('Skapa konto');

        $this->assertSame('sv', session(PublicLocale::SESSION_KEY));

        $this->get(route('filament.app.auth.login'))->assertSee('Kom ihåg mig');
    }

    public function test_the_login_page_links_back_to_itself(): void
    {
        $this->get(route('filament.app.auth.login'))
            ->assertSee(route('filament.app.auth.login', ['lang' => 'sv']), false);
    }

    public function test_a_signed_in_user_keeps_their_own_language(): void
    {
        $user = User::factory()->create(['locale' => 'en']);
        App::setLocale('en');

        $request = Request::create('/app?lang=sv');
        $request->setLaravelSession($this->app['session.store']);
        $request->setUserResolver(fn () => $user);

        (new SetGuestLocale)->handle($request, fn () => response('ok'));

        $this->assertSame('en', App::getLocale());
    }
}
