<?php

$mailFromAddress = env('MAIL_FROM_ADDRESS', env('MAIL_TRANSACTIONAL_FROM', 'no-reply@pyonea.com'));
$mailFromName = env('MAIL_FROM_NAME', env('MAIL_TRANSACTIONAL_NAME', 'Pyonea'));
$mailReplyAddress = env('MAIL_REPLY_TO_ADDRESS', env('MAIL_SUPPORT_ADDRESS', 'support@pyonea.com'));
$mailReplyName = env('MAIL_REPLY_TO_NAME', env('MAIL_SUPPORT_NAME', 'Pyonea Support'));

$validEmail = static function ($address, $fallback) {
    if (is_string($address) && filter_var($address, FILTER_VALIDATE_EMAIL)) {
        return $address;
    }

    if (is_string($fallback) && filter_var($fallback, FILTER_VALIDATE_EMAIL)) {
        return $fallback;
    }

    return 'hello@example.com';
};

$mailFromAddress = $validEmail($mailFromAddress, null);
$mailReplyAddress = $validEmail($mailReplyAddress, $mailFromAddress);

return [

    /*
    |--------------------------------------------------------------------------
    | Default Mailer
    |--------------------------------------------------------------------------
    |
    | This option controls the default mailer that is used to send all email
    | messages unless another mailer is explicitly specified when sending
    | the message. All additional mailers can be configured within the
    | "mailers" array. Examples of each type of mailer are provided.
    |
    */

    'default' => env('MAIL_MAILER', 'log'),

    /*
    |--------------------------------------------------------------------------
    | Mailer Configurations
    |--------------------------------------------------------------------------
    |
    | Here you may configure all of the mailers used by your application plus
    | their respective settings. Several examples have been configured for
    | you and you are free to add your own as your application requires.
    |
    | Laravel supports a variety of mail "transport" drivers that can be used
    | when delivering an email. You may specify which one you're using for
    | your mailers below. You may also add additional mailers if needed.
    |
    | Supported: "smtp", "sendmail", "mailgun", "ses", "ses-v2",
    |            "postmark", "resend", "log", "array",
    |            "failover", "roundrobin"
    |
    */

    'mailers' => [

        'smtp' => [
            'transport' => 'smtp',
            'scheme' => env('MAIL_SCHEME') ?: (match (env('MAIL_ENCRYPTION')) {
                'ssl', 'smtps' => 'smtps',
                default => 'smtp',
            }),
            'url' => env('MAIL_URL'),
            'host' => env('MAIL_HOST', '127.0.0.1'),
            'port' => env('MAIL_PORT', 2525),
            'username' => env('MAIL_USERNAME'),
            'password' => env('MAIL_PASSWORD'),
            'timeout' => null,
            'local_domain' => env('MAIL_EHLO_DOMAIN', parse_url((string) env('APP_URL', 'http://localhost'), PHP_URL_HOST)),
        ],

        'ses' => [
            'transport' => 'ses',
        ],

        'postmark' => [
            'transport' => 'postmark',
            // 'message_stream_id' => env('POSTMARK_MESSAGE_STREAM_ID'),
            // 'client' => [
            //     'timeout' => 5,
            // ],
        ],

        'resend' => [
            'transport' => 'resend',
        ],

        'sendmail' => [
            'transport' => 'sendmail',
            'path' => env('MAIL_SENDMAIL_PATH', '/usr/sbin/sendmail -bs -i'),
        ],

        'log' => [
            'transport' => 'log',
            'channel' => env('MAIL_LOG_CHANNEL'),
        ],

        'array' => [
            'transport' => 'array',
        ],

        'failover' => [
            'transport' => 'failover',
            'mailers' => [
                'smtp',
                'log',
            ],
            'retry_after' => 60,
        ],

        'roundrobin' => [
            'transport' => 'roundrobin',
            'mailers' => [
                'ses',
                'postmark',
            ],
            'retry_after' => 60,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Global "From" Address
    |--------------------------------------------------------------------------
    |
    | You may wish for all emails sent by your application to be sent from
    | the same address. Here you may specify a name and address that is
    | used globally for all emails that are sent by your application.
    |
    */

    'from' => [
        'address' => $mailFromAddress,
        'name' => $mailFromName,
    ],

    'reply_to' => [
        'address' => $mailReplyAddress,
        'name' => $mailReplyName,
    ],

    'admin_report_email' => $validEmail(env('ADMIN_REPORT_EMAIL'), env('MAIL_SUPPORT_ADDRESS', 'support@pyonea.com')),

    /*
    |--------------------------------------------------------------------------
    | Branded sender identities (From + Reply-To per mail category)
    |--------------------------------------------------------------------------
    |
    | SMTP auth uses MAIL_USERNAME (typically no-reply@pyonea.com). Any other From
    | address here must be an alias on that Namecheap mailbox, or use its own
    | mailbox credentials via a dedicated mailer.
    |
    */
    'addresses' => [
        'transactional' => [
            'address' => $validEmail(env('MAIL_TRANSACTIONAL_FROM', env('MAIL_FROM_ADDRESS', 'no-reply@pyonea.com')), null),
            'name' => env('MAIL_TRANSACTIONAL_NAME', env('MAIL_FROM_NAME', 'Pyonea')),
            'reply_to' => [
                'address' => $validEmail(env('MAIL_SUPPORT_ADDRESS', 'support@pyonea.com'), $mailReplyAddress),
                'name' => env('MAIL_SUPPORT_NAME', $mailReplyName),
            ],
        ],
        'welcome' => [
            'address' => $validEmail(env('MAIL_WELCOME_FROM', 'hi@pyonea.com'), $mailFromAddress),
            'name' => env('MAIL_WELCOME_NAME', 'Pyonea'),
            'reply_to' => [
                'address' => $validEmail(env('MAIL_SUPPORT_ADDRESS', 'support@pyonea.com'), $mailReplyAddress),
                'name' => env('MAIL_SUPPORT_NAME', $mailReplyName),
            ],
        ],
        'contact' => [
            'address' => $validEmail(env('MAIL_CONTACT_ADDRESS', 'contact@pyonea.com'), $mailFromAddress),
            'name' => env('MAIL_CONTACT_NAME', 'Pyonea Contact'),
            'reply_to' => [
                'address' => $validEmail(env('MAIL_SUPPORT_ADDRESS', 'support@pyonea.com'), $mailReplyAddress),
                'name' => env('MAIL_SUPPORT_NAME', $mailReplyName),
            ],
        ],
        'support' => [
            'address' => $validEmail(env('MAIL_SUPPORT_ADDRESS', 'support@pyonea.com'), $mailReplyAddress),
            'name' => env('MAIL_SUPPORT_NAME', $mailReplyName),
            'reply_to' => [
                'address' => $validEmail(env('MAIL_SUPPORT_ADDRESS', 'support@pyonea.com'), $mailReplyAddress),
                'name' => env('MAIL_SUPPORT_NAME', $mailReplyName),
            ],
        ],
        'newsletter' => [
            'address' => $validEmail(env('MAIL_NEWSLETTER_FROM', env('MAIL_TRANSACTIONAL_FROM', 'no-reply@pyonea.com')), $mailFromAddress),
            'name' => env('MAIL_NEWSLETTER_NAME', 'Pyonea'),
            'reply_to' => [
                'address' => $validEmail(env('MAIL_SUPPORT_ADDRESS', 'support@pyonea.com'), $mailReplyAddress),
                'name' => env('MAIL_SUPPORT_NAME', $mailReplyName),
            ],
        ],
    ],

];
