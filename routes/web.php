<?php

use Illuminate\Support\Facades\Route;

// Serve React app for all non-API routes
Route::get('/{any}', function () {
    $path = public_path('index.html');

    if (!File::exists($path)) {
        abort(404, 'Frontend not built yet');
    }

    return file_get_contents($path);
})->where('any', '^(?!api/).*$');