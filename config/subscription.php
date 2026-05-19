<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Bulk Import Settings
    |--------------------------------------------------------------------------
    */

    'bulk_import' => [
        // Maximum number of rows allowed per CSV/Excel upload.
        // Override per environment via .env: SUBSCRIPTION_BULK_IMPORT_MAX_ROWS=200
        'max_rows' => (int) env('SUBSCRIPTION_BULK_IMPORT_MAX_ROWS', 500),
    ],

    /*
    |--------------------------------------------------------------------------
    | Expiry Command Settings
    |--------------------------------------------------------------------------
    */

    'expiry' => [
        // Send an admin alert email when the expire command has failures.
        'alert_on_failure' => (bool) env('SUBSCRIPTION_EXPIRY_ALERT', true),
    ],

];