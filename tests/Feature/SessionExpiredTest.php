<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class SessionExpiredTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // The CSRF check is skipped in tests: this route plays the "419 Page Expired" of a stale form.
        Route::middleware('web')->post('/_test/expired-form', fn () => throw new TokenMismatchException('CSRF token mismatch.'));
    }

    public function test_expired_form_while_logged_in_goes_back_to_the_form_with_its_inputs(): void
    {
        $this->actingAsAdmin();
        $form = route('erp.client-invoices.index');

        $this->from($form)->post('/_test/expired-form', ['reference' => 'DV-12', 'password' => 'secret'])
            ->assertRedirect($form)
            ->assertSessionHas('warning')
            ->assertSessionHasInput('reference', 'DV-12')
            ->assertSessionMissing('_old_input.password');

        $this->get($form)->assertSee('La page avait expire');
    }

    public function test_expired_session_goes_to_login_then_back_to_the_page(): void
    {
        $page = route('erp.client-invoices.index');

        $this->from($page)->post('/_test/expired-form')
            ->assertRedirect(route('login'))
            ->assertSessionHas('url.intended', $page);

        $this->get(route('login'))->assertSee('Votre session a expire');

        $user = $this->userWithRole('Admin');
        $this->post(route('login'), ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect($page);
    }

    public function test_expired_login_form_keeps_the_email(): void
    {

        $this->from(route('login'))->post('/_test/expired-form', ['email' => 'a@b.c', 'password' => 'x'])
            ->assertRedirect(route('login'))
            ->assertSessionHasInput('email', 'a@b.c')
            ->assertSessionMissing('url.intended');
    }

    public function test_opening_a_page_after_the_session_ended_explains_why(): void
    {
        $this->get(route('erp.client-invoices.index'))
            ->assertRedirect(route('login'))
            ->assertSessionHas('warning');

        $this->get('/')->assertRedirect(route('login'))->assertSessionMissing('warning');
    }

    public function test_error_pages_are_in_french_with_a_way_back(): void
    {
        $this->get('/this-page-does-not-exist')
            ->assertNotFound()
            ->assertSee('Page introuvable')
            ->assertSee('Retour a l')
            ->assertDontSee('Not Found');

        $this->actingAs($this->userWithRole('Technicien'));
        $this->get(route('users.index'))->assertForbidden()->assertSee('Acces refuse');
    }

    public function test_expired_ajax_call_gets_a_json_message(): void
    {
        $this->postJson('/_test/expired-form')->assertStatus(419)->assertJsonStructure(['message']);
    }
}
