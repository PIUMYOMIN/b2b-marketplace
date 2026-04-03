<?php
namespace App\Notifications;
use App\Models\ProductReview;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewProductReview extends Notification
{
    public function __construct(public ProductReview $review)
    {
    }
    public function via($notifiable): array
    {
        $channels = ['database'];
        if (!empty($notifiable->email) && $this->shouldSend($notifiable)) {
            $channels[] = 'mail';
        }
        return $channels;
    }
    public function toMail($n)
    {
        return (new MailMessage)
            ->subject("New " . str_repeat('★', $this->review->rating) . " Review on \"{$this->review->product?->name_en}\"")
            ->view('emails.product-review', ['review' => $this->review->load('product', 'user'), 'seller' => $n]);
    }
    public function toArray($n): array
    {
        return ['type' => 'product_review', 'review_id' => $this->review->id, 'rating' => $this->review->rating, 'product_name' => $this->review->product?->name_en, 'message' => "New {$this->review->rating}-star review on \"{$this->review->product?->name_en}\"."];
    }
    public function shouldSend($user): bool
    {
        $prefs = $user->notification_preferences ?? [];
        return $prefs['review_notifications'] ?? true;
    }
}