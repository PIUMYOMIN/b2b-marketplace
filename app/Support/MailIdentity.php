<?php

namespace App\Support;

use Illuminate\Mail\Mailable;
use Illuminate\Notifications\Messages\MailMessage;

class MailIdentity
{
    /**
     * @return array{address: string, name: string, reply_to: array{address: string, name: string}}
     */
    public static function resolve(string $type = 'transactional'): array
    {
        $identity = config("mail.addresses.{$type}");

        if (!is_array($identity) || empty($identity['address'])) {
            $identity = config('mail.addresses.transactional', []);
        }

        $address = self::validEmail($identity['address'] ?? null, config('mail.from.address'));
        $replyTo = $identity['reply_to'] ?? config('mail.reply_to', []);

        return [
            'address' => $address,
            'name' => (string) ($identity['name'] ?? config('mail.from.name', 'Pyonea')),
            'reply_to' => [
                'address' => self::validEmail($replyTo['address'] ?? null, config('mail.reply_to.address', $address)),
                'name' => (string) ($replyTo['name'] ?? config('mail.reply_to.name', 'Pyonea Support')),
            ],
        ];
    }

    public static function apply(Mailable $mail, string $type = 'transactional'): Mailable
    {
        $identity = self::resolve($type);

        return $mail
            ->from($identity['address'], $identity['name'])
            ->replyTo($identity['reply_to']['address'], $identity['reply_to']['name']);
    }

    public static function applyToMailMessage(MailMessage $message, string $type = 'transactional'): MailMessage
    {
        $identity = self::resolve($type);

        return $message
            ->from($identity['address'], $identity['name'])
            ->replyTo($identity['reply_to']['address'], $identity['reply_to']['name']);
    }

    public static function inbox(string $type): string
    {
        return self::resolve($type)['address'];
    }

    private static function validEmail(?string $address, ?string $fallback): string
    {
        if (is_string($address) && filter_var($address, FILTER_VALIDATE_EMAIL)) {
            return $address;
        }

        if (is_string($fallback) && filter_var($fallback, FILTER_VALIDATE_EMAIL)) {
            return $fallback;
        }

        return 'no-reply@pyonea.com';
    }
}
