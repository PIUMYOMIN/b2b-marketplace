<?php

// ============================================================================
// ADD THESE IMPORTS to the top of routes/api.php
// ============================================================================

use App\Http\Controllers\Api\ProductOptionController;
use App\Http\Controllers\Api\ProductVariantController;


// ============================================================================
// ADD THESE ROUTES inside the seller prefix group in routes/api.php
// (alongside the existing seller products routes)
// ============================================================================

// ── Product Options ──────────────────────────────────────────────────────────
// Seller defines the buyer-selectable options (Color, Size, etc.)
// and their predefined values before generating/creating variants.
Route::prefix('products/{product}/options')->group(function () {
    Route::get('/',    [ProductOptionController::class, 'index']);     // List all options + values
    Route::post('/',   [ProductOptionController::class, 'store']);     // Replace all options + values
    Route::delete('/', [ProductOptionController::class, 'destroyAll']); // Remove all options + variants
});

// ── Product Variants ─────────────────────────────────────────────────────────
Route::prefix('products/{product}/variants')->group(function () {
    Route::get('/',                         [ProductVariantController::class, 'index']);    // List all variants
    Route::post('/generate',                [ProductVariantController::class, 'generate']); // Auto-generate all combinations
    Route::post('/',                        [ProductVariantController::class, 'store']);    // Manually add one variant
    Route::put('/{variant}',                [ProductVariantController::class, 'update']);   // Edit price/qty/sku/moq
    Route::delete('/{variant}',             [ProductVariantController::class, 'destroy']); // Soft-delete a variant
    Route::patch('/{variant}/toggle',       [ProductVariantController::class, 'toggle']);  // Toggle is_active
});