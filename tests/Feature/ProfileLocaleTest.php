<?php

namespace Tests\Feature;

use App\Filament\Pages\Auth\EditProfile;
use App\Http\Middleware\SetUserLocale;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Livewire\Livewire;
use Tests\TestCase;

class ProfileLocaleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('app');
    }

    public function test_a_new_user_defaults_to_english(): void
    {
        $user = User::factory()->create();

        $this->assertSame('en', $user->fresh()->locale);
        $this->assertSame('en', $user->preferredLocale());
    }

    public function test_the_profile_page_saves_the_selected_language(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test(EditProfile::class)
            ->assertSchemaStateSet(['locale' => 'en'])
            ->fillForm(['locale' => 'sv'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('sv', $user->fresh()->locale);
    }

    public function test_an_unsupported_language_is_rejected(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test(EditProfile::class)
            ->fillForm(['locale' => 'de'])
            ->call('save')
            ->assertHasFormErrors(['locale']);

        $this->assertSame('en', $user->fresh()->locale);
    }

    public function test_the_middleware_applies_the_users_language_to_the_request(): void
    {
        $user = User::factory()->create(['locale' => 'sv']);

        $request = Request::create('/app');
        $request->setUserResolver(fn () => $user);

        (new SetUserLocale)->handle($request, fn () => response('ok'));

        $this->assertSame('sv', app()->getLocale());
        $this->assertSame('Språk', __('profile.locale.label'));
    }
}
