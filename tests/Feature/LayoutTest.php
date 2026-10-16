<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LayoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_page_loader_is_on_public_and_application_pages(): void
    {
        $this->get(route('login'))->assertOk()->assertSee('id="erp-page-loader"', false);

        $this->actingAsAdmin();
        $this->get(route('dashboard'))->assertOk()->assertSee('id="erp-page-loader"', false);
        $this->get(route('erp.client-invoices.index'))->assertOk()->assertSee('id="erp-page-loader"', false);
    }

    public function test_layout_is_always_french_left_to_right(): void
    {
        $this->actingAsAdmin();

        // The language switcher was removed (2026-09-28): French only, even with an old session value.
        $this->withSession(['locale' => 'ar'])->get(route('erp.client-invoices.index'))
            ->assertSee('dir="ltr"', false)
            ->assertSee('assets/css/style.css', false)
            ->assertDontSee('assets/css-rtl/style.css', false);
    }

    public function test_template_demo_panels_and_dead_profile_links_are_gone(): void
    {
        $this->actingAsAdmin();

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Valex', false)
            ->assertDontSee('Boite de reception')
            ->assertDontSee('setInterval', false)
            ->assertSee('Deconnexion');
    }
}
