<?php

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;
use Spatie\Permission\Models\Role;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication {
        createApplication as baseCreateApplication;
    }

    public function createApplication()
    {
        $app = $this->baseCreateApplication();

        // Safety net: RefreshDatabase wipes tables, so refuse to run on the real database.
        $config = $app['config'];
        $database = $config->get('database.connections.' . $config->get('database.default') . '.database');
        if ($database === 'laravel') {
            throw new RuntimeException('Tests refused: they would run on the real database "laravel". Use laravel_testing (see phpunit.xml).');
        }

        return $app;
    }

    protected function userWithRole(string $role): User
    {
        $user = User::factory()->create(['role_name' => [$role]]);
        $user->assignRole(Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']));

        return $user;
    }

    protected function actingAsAdmin(): User
    {
        $user = $this->userWithRole('Admin');
        $this->actingAs($user);

        return $user;
    }
}
