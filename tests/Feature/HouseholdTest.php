<?php

namespace Tests\Feature;

use App\Actions\Households\CreatePersonalHousehold;
use App\Filament\Pages\MyHousehold;
use App\Models\Household;
use App\Models\User;
use Filament\Auth\Pages\Register;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class HouseholdTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('app');
    }

    public function test_a_household_is_named_after_the_last_name(): void
    {
        $this->assertSame('The Krona household', Household::defaultNameFor('Eric  Krona'));
        $this->assertSame('The Krona household', Household::defaultNameFor('Anna Maria Krona'));
        $this->assertSame('The Cher household', Household::defaultNameFor('Cher'));
        $this->assertSame('Hushållet Krona', Household::defaultNameFor('Eric Krona', 'sv'));
    }

    public function test_existing_users_get_a_household_of_their_own_when_migrating(): void
    {
        $eric = User::factory()->create(['name' => 'Eric Krona', 'locale' => 'sv']);
        $jane = User::factory()->create(['name' => 'Jane Doe']);
        $alreadyHasOne = User::factory()->withPersonalTeam()->create();

        (require database_path('migrations/2026_09_25_000050_create_households_for_existing_users.php'))->up();

        $this->assertSame('Hushållet Krona', $eric->fresh()->currentTeam->name);
        $this->assertSame('The Doe household', $jane->fresh()->currentTeam->name);
        $this->assertTrue($eric->fresh()->currentTeam->personal_household);
        $this->assertTrue($eric->fresh()->ownsTeam($eric->fresh()->currentTeam));
        $this->assertSame(1, $alreadyHasOne->ownedTeams()->count());
    }

    public function test_creating_a_household_twice_keeps_the_first(): void
    {
        $user = User::factory()->create(['name' => 'Eric Krona']);

        $first = app(CreatePersonalHousehold::class)->handle($user);
        $second = app(CreatePersonalHousehold::class)->handle($user->fresh());

        $this->assertTrue($first->is($second));
        $this->assertSame(1, Household::count());
    }

    public function test_registering_in_the_panel_creates_a_household(): void
    {
        Livewire::test(Register::class)
            ->fillForm([
                'name' => 'Eric Krona',
                'email' => 'eric@example.com',
                'password' => 'password-123',
                'passwordConfirmation' => 'password-123',
            ])
            ->call('register')
            ->assertHasNoFormErrors();

        $user = User::where('email', 'eric@example.com')->firstOrFail();

        $this->assertSame('The Krona household', $user->currentTeam->name);
        $this->assertTrue($user->currentTeam->personal_household);
    }

    public function test_the_household_page_is_in_the_panel(): void
    {
        $user = User::factory()->create(['name' => 'Eric Krona']);
        app(CreatePersonalHousehold::class)->handle($user);

        $this->actingAs($user->fresh())
            ->get(MyHousehold::getUrl())
            ->assertOk()
            ->assertSee('The Krona household')
            // Rendered in the panel's own layout, sidebar included.
            ->assertSee('fi-sidebar', escape: false);
    }

    public function test_jetstreams_own_pages_lead_to_the_panel(): void
    {
        $user = User::factory()->create();
        $household = app(CreatePersonalHousehold::class)->handle($user);

        $this->get('/login')->assertRedirect('/app/login');

        $this->actingAs($user->fresh());
        $this->get('/teams/'.$household->getKey())->assertRedirect('/app/household');
        $this->get('/teams/create')->assertRedirect('/app/household');
        $this->get('/user/profile')->assertRedirect('/app/profile');
        $this->get('/team-invitations/1')->assertNotFound();
    }
}
