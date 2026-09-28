<?php

namespace Tests\Feature;

use App\Actions\Households\CreatePersonalHousehold;
use App\Filament\Pages\Auth\EditProfile;
use App\Filament\Pages\Auth\Register;
use App\Models\Recipe;
use App\Models\User;
use Filament\Auth\Notifications\VerifyEmail;
use Filament\Auth\Notifications\VerifyEmailChange;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Sign-up is open, so an account proves its address before it can use the panel or the few
 * signed-in routes outside it. Accounts from before the check are grandfathered in.
 */
class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('app');
    }

    private function prompt(): string
    {
        return route('filament.app.auth.email-verification.prompt');
    }

    private function userWithHousehold(array $attributes = [], bool $verified = true): User
    {
        $user = User::factory()->when(! $verified, fn ($factory) => $factory->unverified())->create($attributes);
        app(CreatePersonalHousehold::class)->handle($user);

        return $user->fresh();
    }

    public function test_registering_sends_a_verification_link_in_the_users_language(): void
    {
        Notification::fake();

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
        $user->update(['locale' => 'sv']);

        $this->assertFalse($user->hasVerifiedEmail());
        Notification::assertSentTo($user, VerifyEmail::class);

        // Queued notifications are sent in the notifiable's preferredLocale().
        $user->notify(new VerifyEmail);
        Notification::assertSentTo($user, VerifyEmail::class, fn ($notification, $channels, $notifiable, $locale) => $locale === 'sv');

        $notification = new VerifyEmail;
        $notification->url = 'https://example.com/verify';
        $this->app->setLocale('sv');
        $this->assertSame('Bekräfta din e-postadress', $notification->toMail($user)->subject);
    }

    public function test_an_unverified_user_is_kept_out_of_the_panel(): void
    {
        $this->actingAs($this->userWithHousehold(verified: false));

        $this->get('/app')->assertRedirect($this->prompt());
        $this->get('/app/household')->assertRedirect($this->prompt());
        $this->get($this->prompt())->assertOk();
    }

    public function test_verifying_opens_the_panel(): void
    {
        $user = $this->userWithHousehold(verified: false);
        $this->actingAs($user);

        $this->get(Filament::getVerifyEmailUrl($user))->assertRedirect();

        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        $this->get('/app')->assertOk();
    }

    public function test_saving_a_shared_recipe_needs_a_verified_address(): void
    {
        $owner = $this->userWithHousehold();
        $recipe = Recipe::create(['owner_user_id' => $owner->getKey(), 'default_locale' => 'en', 'visibility' => 'public']);
        $recipe->revisions()->create([
            'locale' => 'en', 'version_number' => 1, 'status' => 'published',
            'title' => 'Waffles', 'created_by_user_id' => $owner->getKey(),
        ]);
        $visitor = $this->userWithHousehold(verified: false);

        $this->actingAs($visitor)
            ->post(route('recipes.share.save', $recipe->uuid))
            ->assertRedirect($this->prompt());
        $this->get(route('recipes.share.sign-in', $recipe->uuid))->assertRedirect($this->prompt());

        $this->assertFalse($recipe->savedByHouseholds()->exists());
    }

    public function test_an_invited_newcomer_verifies_and_lands_back_on_the_invitation(): void
    {
        $eric = $this->userWithHousehold(['name' => 'Eric Krona']);
        $anna = $this->userWithHousehold(['name' => 'Anna Svensson', 'email' => 'anna@example.com'], verified: false);
        $invitation = $eric->currentTeam->teamInvitations()->create(['email' => 'anna@example.com', 'role' => 'editor']);
        $accept = URL::signedRoute('households.invitations.accept', ['invitation' => $invitation]);

        $this->actingAs($anna)->get($accept)->assertRedirect($this->prompt());
        $this->assertFalse($anna->fresh()->belongsToTeam($eric->currentTeam));

        // The verification link sends them back to where they were headed.
        $this->get(Filament::getVerifyEmailUrl($anna))->assertRedirect($accept);
        $this->get($accept)->assertRedirect();

        $this->assertTrue($anna->fresh()->belongsToTeam($eric->currentTeam));
        $this->assertModelMissing($invitation);
    }

    public function test_changing_the_address_waits_for_the_new_one_to_be_confirmed(): void
    {
        Notification::fake();
        $user = $this->userWithHousehold(['email' => 'old@example.com']);
        $this->actingAs($user);

        Livewire::test(EditProfile::class)
            ->fillForm(['email' => 'ek@itomat.se', 'currentPassword' => 'password'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('old@example.com', $user->fresh()->email);
        $this->assertFalse($user->fresh()->isCatalogAdmin());
        Notification::assertSentOnDemand(VerifyEmailChange::class);
    }

    public function test_existing_accounts_are_grandfathered_in(): void
    {
        $unverified = User::factory()->unverified()->create();
        $verified = User::factory()->create(['email_verified_at' => now()->subYear()]);

        (require database_path('migrations/2026_09_26_000100_verify_existing_users_emails.php'))->up();

        $this->assertTrue($unverified->fresh()->hasVerifiedEmail());
        $this->assertTrue($verified->fresh()->email_verified_at->isSameDay(now()->subYear()), 'an earlier verification is kept');
    }
}
