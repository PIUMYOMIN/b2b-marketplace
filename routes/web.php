<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\FrontendController;

// All non‑API routes go to the frontend controller
Route::get('/{any?}', [FrontendController::class, 'index'])
    ->where('any', '^(?!api/).*$');
