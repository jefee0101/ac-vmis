<?php

return [

    /*
    |--------------------------------------------------------------------------
     | Default Mailer
    |--------------------------------------------------------------------------
     |
    | The application sends transactional emails through the Brevo API
    | service layer. The Brevo DSN mailer is defined here for config
    | consistency, while the app-level sender continues to use the API
    | service directly.
     |
     */

    'default' => env('MAIL_MAILER', 'log'),

    /*
    |--------------------------------------------------------------------------
    | Mailer Configurations
    |--------------------------------------------------------------------------
    |
    | The Brevo mailer is declared here for configuration parity with the
    | deployed environment. Fallback mailers remain available for local
    | debugging and framework compatibility.
    |
    */

    'mailers' => [
        'brevo' => [
            'transport' => 'symfony',
            'dsn' => env('MAIL_DSN'),
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
        'address' => env('MAIL_FROM_ADDRESS', 'hello@example.com'),
        'name' => env('MAIL_FROM_NAME', 'Example'),
    ],

];
