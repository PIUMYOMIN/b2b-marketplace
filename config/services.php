<?php
// config/services.php
// Payment gateway credentials — all sourced from .env

return [

    'mailgun' => [
        'domain'   => env('MAILGUN_DOMAIN'),
        'secret'   => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme'   => 'https',
    ],

    'postmark' => ['token' => env('POSTMARK_TOKEN')],

    'ses' => [
        'key'    => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    // ── Myanmar Payment Gateways ───────────────────────────────────────────────

    'myanpay' => [
        'merchant_id'    => env('MyanPay_MERCHANT_ID'),
        'merchant_key'   => env('MyanPay_MERCHANT_KEY'),
        'api_url'        => env('MyanPay_API_URL', 'https://api.mmqr.com.mm/v1'),
        'webhook_secret' => env('MyanPay_WEBHOOK_SECRET'),
    ],

    'mmqr' => [
        'app_id'         => env('MYANMYANPAY_APP_ID', env('MMPAY_APP_ID')),
        'public_key'     => env('MYANMYANPAY_PUBLIC_KEY', env('MMPAY_PUBLISHABLE_KEY', env('MMQR_PUBLIC_KEY'))),
        'secret_key'     => env('MYANMYANPAY_SECRET_KEY', env('MMPAY_SECRET_KEY', env('MMQR_SECRET_KEY'))),
        'merchant_id'    => env('MMQR_MERCHANT_ID', env('MYANMYANPAY_PUBLIC_KEY')),
        'merchant_key'   => env('MMQR_MERCHANT_KEY', env('MYANMYANPAY_SECRET_KEY')),
        'api_url'        => env('MYANMYANPAY_API_URL', env('MMPAY_API_URL', env('MMQR_API_URL', 'https://ezapi.myanmyanpay.com'))),
        'webhook_secret' => env('MYANMYANPAY_WEBHOOK_SECRET', env('MMQR_WEBHOOK_SECRET')),
    ],

    'kbzpay' => [
        'app_id'        => env('KBZPAY_APP_ID'),
        'app_key'       => env('KBZPAY_APP_KEY'),
        'merchant_code' => env('KBZPAY_MERCHANT_CODE'),
        'api_url'       => env('KBZPAY_API_URL', 'https://api.kbzpay.com/payment/gateway/uat'),
        'webhook_secret'=> env('KBZPAY_WEBHOOK_SECRET'),
    ],

    'wavepay' => [
        'merchant_id'    => env('WAVEPAY_MERCHANT_ID'),
        'secret_key'     => env('WAVEPAY_SECRET_KEY'),
        'api_url'        => env('WAVEPAY_API_URL', 'https://api.wavemoney.com.mm/sandbox/payment'),
        'webhook_secret' => env('WAVEPAY_WEBHOOK_SECRET'),
    ],

    'recaptcha' => [
        'site_key'   => env('RECAPTCHA_SITE_KEY'),
        'secret_key' => env('RECAPTCHA_SECRET_KEY'),
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI'),
    ],

    'facebook' => [
        'client_id' => env('FACEBOOK_CLIENT_ID'),
        'client_secret' => env('FACEBOOK_CLIENT_SECRET'),
        'redirect' => env('FACEBOOK_REDIRECT_URI'),
    ],

];
