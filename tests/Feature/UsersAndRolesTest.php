<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Administration > Utilisateurs / Roles (2026-09-29): ERP-style pages and safeguards. */
class UsersAndRolesTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_users_page_lists_filters_and_creates(): void
    {
        $admin = $this->actingAsAdmin();
        $tech = $this->userWithRole('Technicien');

        $this->get(route('users.index'))->assertOk()
            ->assertSee('Nouvel utilisateur')->assertSee($tech->email)->assertSee('Administrateur')->assertSee('Jamais');
        // (the logged-in admin is also in the header menu: filter checked on another admin)
        $otherAdmin = $this->userWithRole('Admin');
        $this->get(route('users.index', ['role' => 'Technicien']))->assertOk()->assertSee($tech->email)->assertDontSee($otherAdmin->email);

        $this->get(route('users.create'))->assertOk()->assertSee('Role et acces')->assertSee('8 caracteres minimum');
        $this->post(route('users.store'), [
            'name' => 'Sara Bennani', 'email' => 'sara@example.com', 'password' => 'motdepasse1', 'confirm-password' => 'motdepasse1',
            'role_name' => 'Technicien', 'status' => 'active',
        ])->assertRedirect(route('users.index'))->assertSessionHas('highlight');

        $sara = User::where('email', 'sara@example.com')->sole();
        $this->assertTrue($sara->hasRole('Technicien'));
        $this->get(route('users.show', $sara))->assertOk()->assertSee('Activite recente')->assertSee('Operations');
        $this->get(route('users.edit', $sara))->assertOk()->assertSee('Laissez vide pour garder le mot de passe actuel')
            ->assertDontSee("@include('users._form')", false);   // the page is really compiled (title with an apostrophe)
    }

    public function test_short_or_different_passwords_are_refused_in_french(): void
    {
        $this->actingAsAdmin();

        $this->post(route('users.store'), [
            'name' => 'X', 'email' => 'x@example.com', 'password' => 'abc', 'confirm-password' => 'abd', 'role_name' => 'Technicien', 'status' => 'active',
        ])->assertSessionHasErrors(['password' => 'Le mot de passe doit contenir au moins 8 caracteres.']);
    }

    public function test_the_last_active_admin_and_your_own_account_are_protected(): void
    {
        $admin = $this->actingAsAdmin();

        $this->delete(route('users.destroy', $admin))->assertSessionHas('error', 'Vous ne pouvez pas supprimer votre propre compte.');
        $this->put(route('users.update', $admin), ['name' => $admin->name, 'email' => $admin->email, 'role_name' => 'Technicien', 'status' => 'active'])
            ->assertSessionHasErrors('role_name');
        $this->assertTrue($admin->fresh()->hasRole('Admin'));

        // With a second active admin, the first one can be changed.
        $other = $this->userWithRole('Admin');
        $this->put(route('users.update', $admin), ['name' => $admin->name, 'email' => $admin->email, 'role_name' => 'Technicien', 'status' => 'active'])
            ->assertSessionHasNoErrors();
        $this->assertTrue($admin->fresh()->hasRole('Technicien'));
        $this->assertNotNull($other);
    }

    public function test_roles_page_shows_the_rights_and_roles_are_fixed(): void
    {
        $this->actingAsAdmin();

        $this->get(route('roles.index'))->assertOk()
            ->assertSee('Qui peut faire quoi')->assertSee('Validation des factures')->assertSee('Administrateur')->assertSee('Technicien');
        $this->get(route('roles.create'))->assertRedirect(route('roles.index'))->assertSessionHas('warning');
        $this->delete(route('roles.destroy', 1))->assertRedirect(route('roles.index'));
        $this->assertDatabaseHas('roles', ['name' => 'Admin']);
    }

    public function test_only_admins_reach_these_pages(): void
    {
        $this->actingAs($this->userWithRole('Technicien'));

        $this->get(route('users.index'))->assertForbidden();
        $this->get(route('roles.index'))->assertForbidden();
    }
}
