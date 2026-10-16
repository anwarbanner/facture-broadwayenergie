<?php

namespace App\Providers;

use App\Models\Category;
use App\Models\Contact;
use App\Models\Products;
use App\Policies\CategoryPolicy;
use App\Policies\ContactPolicy;
use App\Policies\ProductPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Products::class => ProductPolicy::class,
        Category::class => CategoryPolicy::class,
        Contact::class => ContactPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerPolicies();

        // Finance (validate invoices, credit notes, payments, statements) is reserved to Admin.
        // Technicien keeps the operational flow: products, stock, quotes, orders, deliveries, invoice drafts.
        Gate::define('manage-finance', fn (User $user) => $user->hasRole('Admin'));
    }
}
