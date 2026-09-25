<?php

namespace Tests\Feature;

use App\Actions\Households\CreatePersonalHousehold;
use App\Filament\Pages\MyHousehold;
use App\Mail\HouseholdInvitationMail;
use App\Models\Recipe;
use App\Models\Household;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

class HouseholdPageTest extends TestCase
{
    use RefreshDatabase;

    private User $eric;

    private User $anna;

    private Household $kronas;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('app');

        $this->eric = $this->userWithHousehold('Eric Krona');
        $this->kronas = $this->eric->currentTeam;
        $this->anna = $this->userWithHousehold('Anna Svensson');
        $this->kronas->users()->attach($this->anna);
        $this->anna->switchTeam($this->kronas);
    }

    private function userWithHousehold(string $name): User
    {
        $user = User::factory()->create(['name' => $name]);
        app(CreatePersonalHousehold::class)->handle($user);

        return $user->fresh();
    }

    public function test_it_lists_the_members_owner_first(): void
    {
        $this->actingAs($this->anna->fresh());

        Livewire::test(MyHousehold::class)
            ->assertSeeInOrder(['Eric Krona', 'Anna Svensson'])
            ->assertActionHidden('invite')
            ->assertActionHidden('rename')
            ->assertActionVisible('leave')
            ->assertActionVisible('switch');
    }

    public function test_the_owner_invites_by_email(): void
    {
        Mail::fake();
        $this->actingAs($this->eric);

        Livewire::test(MyHousehold::class)
            ->callAction('invite', data: ['email' => 'jane@example.com'])
            ->assertHasNoActionErrors()
            ->assertSee('jane@example.com');

        $this->assertDatabaseHas('household_invitations', ['household_id' => $this->kronas->id, 'email' => 'jane@example.com']);
        Mail::assertSent(HouseholdInvitationMail::class, fn (HouseholdInvitationMail $mail) => $mail->hasTo('jane@example.com'));
    }

    public function test_members_and_repeat_invitations_are_refused(): void
    {
        Mail::fake();
        $this->actingAs($this->eric);
        $this->kronas->teamInvitations()->create(['email' => 'jane@example.com']);

        Livewire::test(MyHousehold::class)
            ->callAction('invite', data: ['email' => $this->anna->email])
            ->assertHasActionErrors(['email']);
        Livewire::test(MyHousehold::class)
            ->callAction('invite', data: ['email' => 'jane@example.com'])
            ->assertHasActionErrors(['email']);

        Mail::assertNothingSent();
    }

    public function test_the_owner_removes_a_member_and_cancels_an_invitation(): void
    {
        $invitation = $this->kronas->teamInvitations()->create(['email' => 'jane@example.com']);
        $this->actingAs($this->eric);

        Livewire::test(MyHousehold::class)
            ->callAction('removeMember', arguments: ['user' => $this->anna->id])
            ->callAction('cancelInvitation', arguments: ['invitation' => $invitation->id]);

        $this->assertFalse($this->anna->fresh()->belongsToTeam($this->kronas));
        $this->assertModelMissing($invitation);
    }

    public function test_a_member_cannot_remove_others(): void
    {
        $sam = $this->userWithHousehold('Sam Krona');
        $this->kronas->users()->attach($sam);
        $this->actingAs($this->anna->fresh());

        Livewire::test(MyHousehold::class)->assertActionHidden('removeMember');
        $this->assertTrue($sam->fresh()->belongsToTeam($this->kronas));
    }

    public function test_the_owner_renames_the_household(): void
    {
        $this->actingAs($this->eric);

        Livewire::test(MyHousehold::class)->callAction('rename', data: ['name' => 'Kronas & co']);

        $this->assertSame('Kronas & co', $this->kronas->fresh()->name);
    }

    public function test_a_new_household_is_created_and_switched_to(): void
    {
        $this->actingAs($this->anna->fresh());

        Livewire::test(MyHousehold::class)->callAction('create', data: ['name' => 'Svenssons']);

        $anna = $this->anna->fresh();
        $this->assertSame('Svenssons', $anna->currentTeam->name);
        $this->assertTrue($anna->ownsTeam($anna->currentTeam));
    }

    public function test_switching_household(): void
    {
        $this->actingAs($this->anna->fresh());

        Livewire::test(MyHousehold::class)->callAction('switch', data: ['household' => $this->anna->personalTeam()->id]);

        $this->assertSame($this->anna->personalTeam()->id, $this->anna->fresh()->current_household_id);
    }

    public function test_a_member_leaves_and_lands_in_their_own_household(): void
    {
        $this->actingAs($this->anna->fresh());

        Livewire::test(MyHousehold::class)->callAction('leave');

        $anna = $this->anna->fresh();
        $this->assertFalse($anna->belongsToTeam($this->kronas));
        $this->assertSame($anna->personalTeam()->id, $anna->current_household_id);
    }

    public function test_the_owner_cannot_leave_or_delete_their_own_household(): void
    {
        $this->actingAs($this->eric);

        Livewire::test(MyHousehold::class)
            ->assertActionHidden('leave')
            ->assertActionHidden('delete');
    }

    public function test_deleting_a_household_gives_the_recipes_back_to_their_authors(): void
    {
        $this->actingAs($this->anna->fresh());
        Livewire::test(MyHousehold::class)->callAction('create', data: ['name' => 'Svenssons']);
        $svenssons = $this->anna->fresh()->currentTeam;
        $recipe = Recipe::create(['owner_user_id' => $this->anna->id, 'default_locale' => 'en', 'visibility' => 'private']);
        $this->assertSame($svenssons->id, $recipe->household_id);

        Livewire::test(MyHousehold::class)->callAction('delete');

        $this->assertModelMissing($svenssons);
        $this->assertNull($recipe->fresh()->household_id);
        $this->assertTrue(Recipe::accessibleTo($this->anna->fresh())->whereKey($recipe)->exists());
        $this->assertSame($this->anna->personalTeam()->id, $this->anna->fresh()->current_household_id);
    }
}
