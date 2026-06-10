<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\FrontendController;
use App\Http\Controllers\ImageThumbnailController;
use App\Http\Controllers\SitemapController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

// Dynamic sitemap — must be defined BEFORE the catch-all SPA route
Route::get('/sitemap.xml', [SitemapController::class, 'index']);

// On-demand image thumbnails — only hit when the cached file doesn't exist
// yet (Apache serves existing files under public/storage directly).
Route::get('/storage/thumbs/{width}/{path}', [ImageThumbnailController::class, 'show'])
    ->where(['width' => '\d+', 'path' => '.*']);

// SPA catch-all — FrontendController injects server-side meta tags per route
Route::get('/{any?}', [FrontendController::class, 'index'])
    ->where('any', '^(?!api/).*$');
