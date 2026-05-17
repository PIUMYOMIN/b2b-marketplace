<?php

namespace App\Observers;

use App\Models\Product;
use App\Models\ProductReview;

/**
 * Keeps products.average_rating and products.review_count in sync
 * whenever a ProductReview is created, updated, or deleted —
 * regardless of which part of the codebase triggered the change.
 */
class ProductReviewObserver
{
    /**
     * Fired after INSERT or UPDATE.
     * Only recalculate when the status column changed, or on first save.
     */
    public function saved(ProductReview $review): void
    {
        // Skip if status hasn't changed (e.g. comment edit only).
        if (!$review->wasChanged('status') && !$review->wasRecentlyCreated) {
            return;
        }

        $this->syncRating($review->product_id);
    }

    /**
     * Fired after hard-DELETE.
     * If the review was approved, removing it affects the stats.
     */
    public function deleted(ProductReview $review): void
    {
        $this->syncRating($review->product_id);
    }

    // ─────────────────────────────────────────────────────────────────────────

    private function syncRating(int $productId): void
    {
        $product = Product::find($productId);
        if (!$product) return;

        $stats = ProductReview::where('product_id', $productId)
            ->where('status', 'approved')
            ->selectRaw('COUNT(*) as review_count, AVG(rating) as average_rating')
            ->first();

        $product->update([
            'average_rating' => $stats->review_count > 0
                ? round((float) $stats->average_rating, 2)
                : 0.00,
            'review_count' => (int) $stats->review_count,
        ]);
    }
}