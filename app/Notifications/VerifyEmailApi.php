<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\URL;

class VerifyEmailApi extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $verificationUrl = $this->frontendVerificationUrl($notifiable);

        // Generate a fresh 6-digit code each time the email is sent
        $code = $notifiable->generateVerificationCode();

        return (new MailMessage)
            ->subject('Verify Your Email — ' . $code . ' is your code')
            ->view('emails.verify-email', [
                'url' => $verificationUrl,
                'user' => $notifiable,
                'code' => $code,
            ]);
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            //
        ];
    }

    protected function frontendVerificationUrl($notifiable)
    {
        $frontendUrl = config('app.frontend_url');
        $signedUrl = URL::temporarySignedRoute(
            'verification.verify',
            Carbon::now()->addMinutes(Config::get('auth.verification.expire', 60)),
            [
                'id' => $notifiable->getKey(),
                'hash' => sha1($notifiable->getEmailForVerification()),
            ]
        );

        // Extract the query string (expires & signature)
        $query = parse_url($signedUrl, PHP_URL_QUERY);

        // Build frontend URL with id and hash in the path
        return $frontendUrl . '/verify-email/' . $notifiable->getKey() . '/' . sha1($notifiable->getEmailForVerification()) . '?' . $query;
    }
}
