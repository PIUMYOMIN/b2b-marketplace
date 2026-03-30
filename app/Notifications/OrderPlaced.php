<?php
namespace App\Notifications;
use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderPlaced extends Notification
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
            ->subject("Order Confirmed — #{$this->order->order_number}")
            ->view('emails.order-placed', ['order' => $this->order->load('items', 'buyer', 'delivery')]);
    }
    public function toArray($n): array
    {
        return ['type' => 'order_placed', 'order_id' => $this->order->id, 'order_number' => $this->order->order_number, 'message' => "Your order #{$this->order->order_number} has been placed successfully."];
    }
    protected function shouldSend($user): bool
    {
        $prefs = is_array($user->notification_preferences)
            ? $user->notification_preferences
            : json_decode($user->notification_preferences ?? '[]', true);
        return $prefs['order_updates'] ?? true;
    }
}
