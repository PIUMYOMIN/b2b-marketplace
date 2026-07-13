<?php

namespace App\Notifications;

use App\Models\ProductReview;
use App\Notifications\Channels\ExpoPushChannel;
use App\Notifications\Concerns\SendsExpoPush;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewProductReview extends Notification
{
    use SendsExpoPush;

    public function __construct(public ProductReview $review) {}

    public function via($notifiable): array
    {
        $channels = ['database'];

        if (!empty($notifiable->email) && $this->shouldSend($notifiable)) {
            $channels[] = 'mail';
        }

        if ($this->shouldSendReviewPush($notifiable)) {
            $channels[] = ExpoPushChannel::class;
        }

        return $channels;
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('New ' . str_repeat('★', $this->review->rating) . ' Review on "' . ($this->review->product?->name_en ?? 'your product') . '"')
            ->view('emails.product-review', [
                'review' => $this->review->load('product', 'user'),
                'seller' => $notifiable,
            ]);
    }

    public function toArray($notifiable): array
    {
        $productName = $this->review->product?->name_en ?? 'your product';
        $message = "New {$this->review->rating}-star review on \"{$productName}\".";

        return [
            'type' => 'product_review',
            'product_id' => $this->review->product_id,
            'review_id' => $this->review->id,
            'rating' => $this->review->rating,
            'product_name' => $productName,
            'message' => $message,
        ];
    }

    public function toExpoPush($notifiable): array
    {
        $productName = $this->review->product?->name_en ?? 'your product';
        $body = "New {$this->review->rating}-star review on \"{$productName}\".";

        return $this->expoPushPayload(
            'New product review',
            $body,
            'reviews',
            [
                'type' => 'product_review',
                'product_id' => (string) $this->review->product_id,
                'review_id' => (string) $this->review->id,
                'rating' => (string) $this->review->rating,
                'product_name' => $productName,
                'message' => $body,
            ],
        );
    }

    public function shouldSend($user): bool
    {
        $prefs = $user->notification_preferences ?? [];

        if (is_string($prefs)) {
            $prefs = json_decode($prefs, true) ?: [];
        }

        return (bool) ($prefs['review_notifications'] ?? true);
    }
}
