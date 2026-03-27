<?php
namespace App\Notifications;
use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewOrderForSeller extends Notification
{
    use Queueable;
    public function __construct(public Order $order)
    {
    }
    public function via($n): array
    {
        return $this->shouldSend($n) ? ['mail', 'database'] : ['database'];
    }
    public function toMail($n)
    {
        return (new MailMessage)
            ->subject("New Order — #{$this->order->order_number}")
            ->view('emails.new-order-seller', ['order' => $this->order->load('items', 'buyer'), 'seller' => $n]);
    }
    public function toArray($n): array
    {
        return ['type' => 'new_order', 'order_id' => $this->order->id, 'order_number' => $this->order->order_number, 'message' => "New order #{$this->order->order_number} received."];
    }
    private function shouldSend($user): bool
    {
        $prefs = $user->notification_preferences ?? [];
        return $prefs['new_orders'] ?? true;
    }
}