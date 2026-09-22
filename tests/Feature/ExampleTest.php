<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_the_landing_page_links_to_login_and_registration(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Vispa recipe site')
            ->assertSee('Svenska')
            ->assertSee(route('filament.app.auth.login'))
            ->assertSee(route('filament.app.auth.register'));
    }

    public function test_the_registration_form_is_available(): void
    {
        $this->get(route('filament.app.auth.register'))->assertOk();
    }
}
