<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\FrontendController;
use App\Http\Controllers\SitemapController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

// Dynamic sitemap — must be defined BEFORE the catch-all SPA route
Route::get('/sitemap.xml', [SitemapController::class, 'index']);

// SPA catch-all — FrontendController injects server-side meta tags per route
Route::get('/{any?}', [FrontendController::class, 'index'])
    ->where('any', '.*');
