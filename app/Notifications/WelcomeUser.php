<?php
namespace App\Notifications;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WelcomeUser extends Notification {
    use Queueable;
    public function via($n): array { return ['mail','database']; }
    public function toMail($n) {
        return (new MailMessage)
            ->subject("Welcome to Pyonea, {$n->name}!")
            ->view('emails.welcome', ['user'=>$n]);
    }
    public function toArray($n): array {
        return ['type'=>'welcome','message'=>"Welcome to Pyonea, {$n->name}!"];
    }
}
