<?php

namespace Tests\Feature;

use App\Actions\Families\CreatePersonalFamily;
use App\Filament\Pages\Family;
use App\Mail\FamilyInvitation;
use App\Models\Recipe;
use App\Models\Team;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

class FamilyPageTest extends TestCase
{
    use RefreshDatabase;

    private User $eric;

    private User $anna;

    private Team $kronas;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('app');

        $this->eric = $this->userWithFamily('Eric Krona');
        $this->kronas = $this->eric->currentTeam;
        $this->anna = $this->userWithFamily('Anna Svensson');
        $this->kronas->users()->attach($this->anna);
        $this->anna->switchTeam($this->kronas);
    }

    private function userWithFamily(string $name): User
    {
        $user = User::factory()->create(['name' => $name]);
        app(CreatePersonalFamily::class)->handle($user);

        return $user->fresh();
    }

    public function test_it_lists_the_members_owner_first(): void
    {
        $this->actingAs($this->anna->fresh());

        Livewire::test(Family::class)
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

        Livewire::test(Family::class)
            ->callAction('invite', data: ['email' => 'jane@example.com'])
            ->assertHasNoActionErrors()
            ->assertSee('jane@example.com');

        $this->assertDatabaseHas('team_invitations', ['team_id' => $this->kronas->id, 'email' => 'jane@example.com']);
        Mail::assertSent(FamilyInvitation::class, fn (FamilyInvitation $mail) => $mail->hasTo('jane@example.com'));
    }

    public function test_members_and_repeat_invitations_are_refused(): void
    {
        Mail::fake();
        $this->actingAs($this->eric);
        $this->kronas->teamInvitations()->create(['email' => 'jane@example.com']);

        Livewire::test(Family::class)
            ->callAction('invite', data: ['email' => $this->anna->email])
            ->assertHasActionErrors(['email']);
        Livewire::test(Family::class)
            ->callAction('invite', data: ['email' => 'jane@example.com'])
            ->assertHasActionErrors(['email']);

        Mail::assertNothingSent();
    }

    public function test_the_owner_removes_a_member_and_cancels_an_invitation(): void
    {
        $invitation = $this->kronas->teamInvitations()->create(['email' => 'jane@example.com']);
        $this->actingAs($this->eric);

        Livewire::test(Family::class)
            ->callAction('removeMember', arguments: ['user' => $this->anna->id])
            ->callAction('cancelInvitation', arguments: ['invitation' => $invitation->id]);

        $this->assertFalse($this->anna->fresh()->belongsToTeam($this->kronas));
        $this->assertModelMissing($invitation);
    }

    public function test_a_member_cannot_remove_others(): void
    {
        $sam = $this->userWithFamily('Sam Krona');
        $this->kronas->users()->attach($sam);
        $this->actingAs($this->anna->fresh());

        Livewire::test(Family::class)->assertActionHidden('removeMember');
        $this->assertTrue($sam->fresh()->belongsToTeam($this->kronas));
    }

    public function test_the_owner_renames_the_family(): void
    {
        $this->actingAs($this->eric);

        Livewire::test(Family::class)->callAction('rename', data: ['name' => 'Kronas & co']);

        $this->assertSame('Kronas & co', $this->kronas->fresh()->name);
    }

    public function test_a_new_family_is_created_and_switched_to(): void
    {
        $this->actingAs($this->anna->fresh());

        Livewire::test(Family::class)->callAction('create', data: ['name' => 'Svenssons']);

        $anna = $this->anna->fresh();
        $this->assertSame('Svenssons', $anna->currentTeam->name);
        $this->assertTrue($anna->ownsTeam($anna->currentTeam));
    }

    public function test_switching_family(): void
    {
        $this->actingAs($this->anna->fresh());

        Livewire::test(Family::class)->callAction('switch', data: ['family' => $this->anna->personalTeam()->id]);

        $this->assertSame($this->anna->personalTeam()->id, $this->anna->fresh()->current_team_id);
    }

    public function test_a_member_leaves_and_lands_in_their_own_family(): void
    {
        $this->actingAs($this->anna->fresh());

        Livewire::test(Family::class)->callAction('leave');

        $anna = $this->anna->fresh();
        $this->assertFalse($anna->belongsToTeam($this->kronas));
        $this->assertSame($anna->personalTeam()->id, $anna->current_team_id);
    }

    public function test_the_owner_cannot_leave_or_delete_their_own_family(): void
    {
        $this->actingAs($this->eric);

        Livewire::test(Family::class)
            ->assertActionHidden('leave')
            ->assertActionHidden('delete');
    }

    public function test_deleting_a_family_gives_the_recipes_back_to_their_authors(): void
    {
        $this->actingAs($this->anna->fresh());
        Livewire::test(Family::class)->callAction('create', data: ['name' => 'Svenssons']);
        $svenssons = $this->anna->fresh()->currentTeam;
        $recipe = Recipe::create(['owner_user_id' => $this->anna->id, 'default_locale' => 'en', 'visibility' => 'private']);
        $this->assertSame($svenssons->id, $recipe->team_id);

        Livewire::test(Family::class)->callAction('delete');

        $this->assertModelMissing($svenssons);
        $this->assertNull($recipe->fresh()->team_id);
        $this->assertTrue(Recipe::accessibleTo($this->anna->fresh())->whereKey($recipe)->exists());
        $this->assertSame($this->anna->personalTeam()->id, $this->anna->fresh()->current_team_id);
    }
}
