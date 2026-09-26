<?php

namespace Tests\Feature;

use App\Actions\Households\CreatePersonalHousehold;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The panel footer shows how many times production has been deployed as a version, 0.01 per
 * deploy (deploy/server_deploy.sh keeps the count).
 */
class DeployVersionTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_footer_shows_the_deploy_number_as_a_version(): void
    {
        $user = User::factory()->create();
        app(CreatePersonalHousehold::class)->handle($user);
        $this->actingAs($user->fresh());

        config(['app.deploy_number' => 7]);
        $this->get('/app')->assertOk()->assertSee('Vispa 0.07');

        config(['app.deploy_number' => 100]);
        $this->get('/app')->assertOk()->assertSee('Vispa 1.00');

        config(['app.deploy_number' => 123]);
        $this->get('/app')->assertOk()->assertSee('Vispa 1.23');
    }
}
