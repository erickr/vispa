<?php

namespace Tests\Feature;

use App\Actions\Households\CreatePersonalHousehold;
use App\Models\Recipe;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * App\Http\Middleware\SecurityHeaders runs on every web response. Uploaded files under /storage
 * are served by Apache and covered by public/.htaccess instead, which a test cannot reach.
 */
class SecurityHeadersTest extends TestCase
{
    use RefreshDatabase;

    private function assertBaselineHeaders(TestResponse $response): TestResponse
    {
        return $response
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('Content-Security-Policy', "frame-ancestors 'self'; base-uri 'self'; object-src 'none'; form-action 'self'")
            ->assertHeaderMissing('Content-Security-Policy-Report-Only')
            ->assertHeader('Permissions-Policy');
    }

    public function test_the_landing_page_has_the_headers(): void
    {
        $response = $this->assertBaselineHeaders($this->get('/')->assertOk());

        $this->assertStringContainsString('camera=()', $response->headers->get('Permissions-Policy'));
    }

    public function test_a_shared_recipe_page_has_the_headers(): void
    {
        $owner = User::factory()->create();
        $recipe = Recipe::create(['owner_user_id' => $owner->id, 'default_locale' => 'en', 'visibility' => 'public']);
        $recipe->revisions()->create([
            'locale' => 'en',
            'version_number' => 1,
            'status' => 'published',
            'title' => 'Kladdkaka',
            'created_by_user_id' => $owner->id,
        ]);

        $this->assertBaselineHeaders($this->get($recipe->shareUrl())->assertOk()->assertSee('Kladdkaka'));
    }

    public function test_the_panel_login_page_has_the_headers(): void
    {
        $this->assertBaselineHeaders($this->get(route('filament.app.auth.login'))->assertOk());
    }

    public function test_a_signed_in_panel_page_has_the_headers(): void
    {
        $user = User::factory()->create();
        app(CreatePersonalHousehold::class)->handle($user);

        $this->actingAs($user->fresh());

        $this->assertBaselineHeaders($this->get('/app/household')->assertOk());
    }

    public function test_hsts_is_only_sent_over_https_in_production(): void
    {
        $this->get('https://localhost/')->assertOk()->assertHeaderMissing('Strict-Transport-Security');

        $this->app->detectEnvironment(fn () => 'production');

        $this->get('http://localhost/')->assertHeaderMissing('Strict-Transport-Security');
        $this->get('https://localhost/')
            ->assertOk()
            ->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
    }

    public function test_the_policy_comes_from_config(): void
    {
        config([
            'security.csp' => "default-src 'self'",
            'security.csp_report_only' => "script-src 'self'",
        ]);

        $this->get('/')
            ->assertHeader('Content-Security-Policy', "default-src 'self'")
            ->assertHeader('Content-Security-Policy-Report-Only', "script-src 'self'");

        config(['security.csp' => '']);

        $this->get('/')->assertHeaderMissing('Content-Security-Policy');
    }

    public function test_headers_a_response_already_set_are_kept(): void
    {
        Route::get('/_test/framed', fn () => response('ok')
            ->header('X-Frame-Options', 'DENY')
            ->header('Content-Security-Policy', "frame-ancestors 'none'"));

        $this->get('/_test/framed')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Content-Security-Policy', "frame-ancestors 'none'")
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }
}
