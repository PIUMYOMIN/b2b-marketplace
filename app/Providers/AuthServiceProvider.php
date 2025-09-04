<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

// ✅ Add these two lines
use App\Models\User;
use Spatie\Permission\Models\Role;
use App\Policies\UserPolicy;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        User::class => UserPolicy::class,
        Role::class => RolePolicy::class
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();

        // Optional: define any gates here if needed
    }
}