<?php

namespace App\Notifications;

use App\Models\Cart;
use App\Models\Product;
use App\Notifications\Concerns\SendsExpoPush;
use Illuminate\Notifications\Notification;

class AbandonedCartReminder extends Notification
{
    use SendsExpoPush;

    public function __construct(
        public Cart $cart,
        public Product $product,
        public int $cartItemCount = 1,
    ) {}

    public function via(object $notifiable): array
    {
        return array_merge(
            ['database'],
            $this->mobilePushChannels($this->shouldSendCartReminder($notifiable)),
        );
    }

    public function toArray(object $notifiable): array
    {
        $productName = (string) ($this->product->name_en ?: $this->product->name_mm ?: 'your cart item');
        $message = $this->buildMessage($productName);

        return [
            'type' => 'abandoned_cart',
            'cart_id' => (string) $this->cart->id,
            'product_id' => (string) $this->product->id,
            'product_slug' => (string) ($this->product->slug_en ?: $this->product->slug_mm ?: ''),
            'product_name' => $productName,
            'cart_item_count' => (string) $this->cartItemCount,
            'url' => $this->cartDeepLink(),
            'message' => $message,
        ];
    }

    public function toExpoPush(object $notifiable): array
    {
        $productName = (string) ($this->product->name_en ?: $this->product->name_mm ?: 'your cart item');
        $body = $this->buildMessage($productName);

        return $this->expoPushPayload(
            'Complete your order',
            $body,
            'promotions',
            [
                'type' => 'abandoned_cart',
                'cart_id' => (string) $this->cart->id,
                'product_id' => (string) $this->product->id,
                'product_slug' => (string) ($this->product->slug_en ?: $this->product->slug_mm ?: ''),
                'product_name' => $productName,
                'cart_item_count' => (string) $this->cartItemCount,
                'url' => $this->cartDeepLink(),
                'message' => $body,
            ],
        );
    }

    protected function shouldSendCartReminder(object $user): bool
    {
        if (!$this->pushNotificationsEnabled($user)) {
            return false;
        }

        $prefs = $this->notificationPreferences($user);

        return (bool) ($prefs['cart_reminders'] ?? true);
    }

    private function buildMessage(string $productName): string
    {
        if ($this->cartItemCount > 1) {
            return "You still have {$this->cartItemCount} items in your cart, including {$productName}. Tap to finish checkout.";
        }

        return "You left {$productName} in your cart. Tap to finish checkout when you're ready.";
    }

    private function cartDeepLink(): string
    {
        $base = rtrim((string) config('app.frontend_url', 'https://pyonea.com'), '/');

        return "{$base}/cart";
    }
}
