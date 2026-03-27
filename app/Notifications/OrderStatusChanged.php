<?php
namespace App\Notifications;
use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderStatusChanged extends Notification
{
    use Queueable;
    public function __construct(public Order $order, public string $previousStatus)
    {
    }
    public function via($n): array
    {
        return $this->shouldSend($n) ? ['mail', 'database'] : ['database'];
    }
    public function toMail($n)
    {
        return (new MailMessage)
            ->subject("Order Update — #{$this->order->order_number}")
            ->view('emails.order-status-changed', ['order' => $this->order->load('items', 'buyer', 'delivery')]);
    }
    public function toArray($n): array
    {
        return ['type' => 'order_status_changed', 'order_id' => $this->order->id, 'order_number' => $this->order->order_number, 'status' => $this->order->status, 'message' => "Order #{$this->order->order_number} is now " . ucfirst($this->order->status) . "."];
    }
    private function shouldSend($user): bool
    {
        $prefs = $user->notification_preferences ?? [];
        return $prefs['order_updates'] ?? true;
    }
}