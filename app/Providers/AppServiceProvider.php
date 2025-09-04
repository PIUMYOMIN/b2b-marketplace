<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema;


class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register()
{
    // Manually register Spatie middleware
    $this->app->singleton('role', function() {
        return new \Spatie\Permission\Middleware\RoleMiddleware();
    });
    
    $this->app->singleton('permission', function() {
        return new \Spatie\Permission\Middleware\PermissionMiddleware();
    });
    
    $this->app->singleton('role_or_permission', function() {
        return new \Spatie\Permission\Middleware\RoleOrPermissionMiddleware();
    });
}

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Schema::defaultStringLength(191);
    }
}