<?php

namespace App\Providers;

use App\Models\Product;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
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
        /**
         * Product route model binding
         *
         * This project uses slug-based routes publicly, but seller/admin APIs often
         * pass numeric IDs (e.g. /seller/products/1/variants/2). If Product's
         * getRouteKeyName() is set to a slug column, implicit binding by {product}
         * will look up by slug and may resolve the wrong record (or a different
         * seller's product), causing confusing 403s.
         *
         * Rule:
         * - numeric value => bind by primary key `id`
         * - otherwise     => bind by `slug_en`
         */
        Route::bind('product', function ($value) {
            $v = (string) $value;
            if ($v !== '' && ctype_digit($v)) {
                return Product::query()->findOrFail((int) $v);
            }

            return Product::query()->where('slug_en', $v)->firstOrFail();
        });
    }
}