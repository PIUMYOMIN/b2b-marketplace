<?php

namespace App\Notifications;

use App\Models\SellerReview;
use App\Notifications\Concerns\SendsExpoPush;
use Illuminate\Notifications\Notification;

class NewSellerReview extends Notification
{
    use SendsExpoPush;

    public function __construct(public SellerReview $review) {}

    public function via($notifiable): array
    {
        $channels = ['database'];

        $channels = array_merge($channels, $this->mobilePushChannels($this->shouldSendReviewPush($notifiable)));

        return $channels;
    }

    public function toArray($notifiable): array
    {
        $reviewerName = $this->reviewerName();
        $message = $reviewerName
            ? "New {$this->review->rating}-star review from {$reviewerName}."
            : "New {$this->review->rating}-star review on your seller profile.";

        return [
            'type' => 'seller_review',
            'seller_id' => $this->review->seller_id,
            'review_id' => $this->review->id,
            'rating' => $this->review->rating,
            'reviewer_name' => $reviewerName,
            'store_slug' => $this->review->seller?->store_slug,
            'message' => $message,
        ];
    }

    public function toExpoPush($notifiable): array
    {
        $reviewerName = $this->reviewerName();
        $body = $reviewerName
            ? "New {$this->review->rating}-star review from {$reviewerName}."
            : "New {$this->review->rating}-star review on your seller profile.";

        return $this->expoPushPayload(
            'New seller review',
            $body,
            'reviews',
            [
                'type' => 'seller_review',
                'seller_id' => (string) $this->review->seller_id,
                'review_id' => (string) $this->review->id,
                'rating' => (string) $this->review->rating,
                'reviewer_name' => $reviewerName,
                'store_slug' => $this->review->seller?->store_slug,
                'message' => $body,
            ],
        );
    }

    private function reviewerName(): ?string
    {
        $user = $this->review->relationLoaded('user')
            ? $this->review->user
            : $this->review->user()->first();

        if (!$user) {
            return null;
        }

        return $user->name ?: $user->company_name ?: null;
    }
}
