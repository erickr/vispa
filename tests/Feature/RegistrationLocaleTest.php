<?php

namespace Tests\Feature;

use App\Filament\Pages\Auth\Register;
use App\Models\User;
use App\Support\PublicLocale;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Registration asks for the language, starting on the one the visitor picked on the public pages.
 */
class RegistrationLocaleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('app');
    }

    private function register(array $data = []): User
    {
        Livewire::test(Register::class)
            ->fillForm($data + [
                'name' => 'Eric Krona',
                'email' => 'eric@example.com',
                'password' => 'password-123',
                'passwordConfirmation' => 'password-123',
            ])
            ->call('register')
            ->assertHasNoFormErrors();

        return User::where('email', 'eric@example.com')->firstOrFail();
    }

    public function test_the_panel_uses_the_registration_page_with_a_language_picker(): void
    {
        $this->get(route('filament.app.auth.register'))
            ->assertOk()
            ->assertSeeLivewire(Register::class);
    }

    public function test_the_language_starts_on_the_one_picked_on_the_landing_page(): void
    {
        $this->get(route('landing', ['lang' => 'sv']))->assertOk();

        $this->assertSame('sv', session(PublicLocale::SESSION_KEY));

        Livewire::test(Register::class)->assertSchemaStateSet(['locale' => 'sv']);
    }

    public function test_without_a_pick_the_language_follows_the_browser(): void
    {
        Livewire::withHeaders(['Accept-Language' => 'sv-SE,sv;q=0.9,en;q=0.5'])
            ->test(Register::class)
            ->assertSchemaStateSet(['locale' => 'sv']);
    }

    public function test_the_chosen_language_is_saved_and_names_the_household(): void
    {
        $user = $this->register(['locale' => 'sv']);

        $this->assertSame('sv', $user->locale);
        $this->assertSame('Hushållet Krona', $user->personalTeam()->name);
    }

    public function test_the_visitor_can_pick_another_language_than_the_suggested_one(): void
    {
        session([PublicLocale::SESSION_KEY => 'sv']);

        $this->assertSame('en', $this->register(['locale' => 'en'])->locale);
    }

    public function test_an_unsupported_language_is_rejected(): void
    {
        Livewire::test(Register::class)
            ->fillForm([
                'name' => 'Eric Krona',
                'email' => 'eric@example.com',
                'locale' => 'de',
                'password' => 'password-123',
                'passwordConfirmation' => 'password-123',
            ])
            ->call('register')
            ->assertHasFormErrors(['locale']);

        $this->assertDatabaseMissing('users', ['email' => 'eric@example.com']);
    }
}
