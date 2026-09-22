<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_the_landing_page_links_to_login_and_registration(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee(route('filament.app.auth.login'))
            ->assertSee(route('filament.app.auth.register'));
    }

    public function test_the_registration_form_is_available(): void
    {
        $this->get(route('filament.app.auth.register'))->assertOk();
    }

    public function test_the_browser_language_is_detected(): void
    {
        $this->withHeader('Accept-Language', 'sv-SE,sv;q=0.9,en;q=0.5')
            ->get('/')
            ->assertSee('<html lang="sv">', false)
            ->assertSee('Vispa receptsajt');

        $this->withHeader('Accept-Language', 'de-DE,de;q=0.9')
            ->get('/')
            ->assertSee('<html lang="en">', false)
            ->assertSee('Vispa recipe site');
    }

    public function test_the_toggle_overrides_the_browser_and_is_remembered(): void
    {
        $this->withHeader('Accept-Language', 'en-US,en')
            ->get('/?lang=sv')
            ->assertSee('Vispa receptsajt');

        $this->withHeader('Accept-Language', 'en-US,en')
            ->get('/')
            ->assertSee('Vispa receptsajt');
    }

    public function test_an_unknown_language_in_the_toggle_is_ignored(): void
    {
        $this->withHeader('Accept-Language', 'sv')
            ->get('/?lang=xx')
            ->assertSee('Vispa receptsajt');
    }
}
