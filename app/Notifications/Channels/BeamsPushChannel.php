<?php

namespace App\Notifications\Channels;

use App\Services\BeamsPushService;
use Illuminate\Notifications\Notification;

class BeamsPushChannel
{
    public function __construct(private BeamsPushService $beams) {}

    public function send(object $notifiable, Notification $notification): void
    {
        if (!$this->beams->isConfigured() || !config('services.beams.enabled', false)) {
            return;
        }

        if (!method_exists($notification, 'toExpoPush')) {
            return;
        }

        /** @var array<string, mixed> $message */
        $message = $notification->toExpoPush($notifiable);
        if (empty($message['title']) && empty($message['body'])) {
            return;
        }

        $this->beams->sendToUser((int) $notifiable->id, $message);
    }
}
