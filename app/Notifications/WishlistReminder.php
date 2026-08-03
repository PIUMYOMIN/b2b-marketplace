<?php

namespace App\Notifications;

use App\Models\Product;
use App\Models\Wishlist;
use App\Notifications\Concerns\SendsExpoPush;
use Illuminate\Notifications\Notification;

class WishlistReminder extends Notification
{
    use SendsExpoPush;

    public function __construct(
        public Wishlist $wishlist,
        public Product $product,
    ) {}

    public function via(object $notifiable): array
    {
        $channels = ['database'];

        return array_merge(
            $channels,
            $this->mobilePushChannels($this->shouldSendWishlistReminder($notifiable)),
        );
    }

    public function toArray(object $notifiable): array
    {
        $productName = (string) ($this->product->name_en ?: $this->product->name_mm ?: 'your saved product');

        return [
            'type' => 'wishlist_reminder',
            'product_id' => (string) $this->product->id,
            'product_slug' => (string) ($this->product->slug_en ?: $this->product->slug_mm ?: ''),
            'product_name' => $productName,
            'url' => $this->productDeepLink(),
            'message' => "Still thinking about {$productName}? It's waiting on your wishlist.",
        ];
    }

    public function toExpoPush(object $notifiable): array
    {
        $productName = (string) ($this->product->name_en ?: $this->product->name_mm ?: 'your saved product');
        $body = "Still thinking about {$productName}? Tap to view it on your wishlist.";

        return $this->expoPushPayload(
            'Still on your wishlist',
            $body,
            'promotions',
            [
                'type' => 'wishlist_reminder',
                'product_id' => (string) $this->product->id,
                'product_slug' => (string) ($this->product->slug_en ?: $this->product->slug_mm ?: ''),
                'product_name' => $productName,
                'url' => $this->productDeepLink(),
                'message' => $body,
            ],
        );
    }

    protected function shouldSendWishlistReminder(object $user): bool
    {
        if (!$this->pushNotificationsEnabled($user)) {
            return false;
        }

        $prefs = $this->notificationPreferences($user);

        return (bool) ($prefs['wishlist_reminders'] ?? true);
    }

    private function productDeepLink(): string
    {
        $base = rtrim((string) config('app.frontend_url', 'https://pyonea.com'), '/');
        $slug = (string) ($this->product->slug_en ?: $this->product->slug_mm ?: '');

        if ($slug !== '') {
            return "{$base}/products/{$slug}";
        }

        return "{$base}/wishlist";
    }
}
