<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Role;

class CustomRouteProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // parent::boot();
        Route::model('role', Role::class);

        Route::middleware('api')
            ->prefix('api')
            ->group(base_path('routes/api.php'));
    }
}