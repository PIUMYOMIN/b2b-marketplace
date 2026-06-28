<?php

namespace App\Http\Resources\Concerns;

use App\Models\Product;
use App\Models\SellerProfile;

trait FormatsMarketplaceTrust
{
    /**
     * @return array<string, mixed>
     */
    protected function productTrustFields(Product $product): array
    {
        return [
            'approval_status'          => $product->status,
            'is_pending_review'        => $product->isPendingReview(),
            'can_checkout'             => $product->canCheckout(),
            'checkout_blocked_reason'  => $product->checkoutBlockedReason(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function sellerTrustFields(?SellerProfile $profile): array
    {
        if (! $profile) {
            return [
                'verified'             => false,
                'trust_badge'          => ['key' => 'new_seller', 'label' => 'New Seller'],
                'status'               => null,
                'verification_status'  => null,
            ];
        }

        return [
            'verified'             => $profile->isVerifiedForDisplay(),
            'trust_badge'          => $profile->trustBadge(),
            'status'               => $profile->status,
            'verification_status'  => $profile->verification_status,
        ];
    }
}
