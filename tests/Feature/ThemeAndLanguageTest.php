<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** 2026-09-28: French only (no language switcher), light / dark mode button. */
class ThemeAndLanguageTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_header_has_the_dark_mode_button_and_no_language_menu(): void
    {
        $this->actingAsAdmin();

        $this->get(route('dashboard'))->assertOk()
            ->assertSee('data-theme-toggle', false)
            ->assertSee('Mode sombre')
            ->assertSee('erp-dark.css', false)
            ->assertSee("localStorage.getItem('erp-theme')", false)   // theme applied before the first paint
            ->assertDontSee('English')
            ->assertDontSee('fe-globe', false);
    }

    public function test_the_login_page_always_stays_light(): void
    {
        // Designed on white cards: dark mode (device or saved choice) must not turn its fields black.
        $this->get(route('login'))->assertOk()
            ->assertSee("document.documentElement.setAttribute('data-theme', 'light');", false)
            ->assertSee('auth-pro.css?v=', false);
    }

    public function test_the_application_stays_in_french(): void
    {
        $this->actingAsAdmin();

        $this->withSession(['locale' => 'en'])->get(route('dashboard'))->assertOk()->assertSee('lang="fr"', false);
        $this->get('/en')->assertNotFound();
        $this->get('/dashboard/ar')->assertNotFound();
    }
}
