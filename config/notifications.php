<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Email Delivery
    |--------------------------------------------------------------------------
    |
    | The notification service can defer transactional email work until the
    | response cycle finishes. Keep this enabled in normal environments so
    | requests stay responsive, and disable it only when debugging.
    |
    */

    'defer_email_delivery' => env('NOTIFICATIONS_DEFER_EMAIL', true),

    /*
    |--------------------------------------------------------------------------
    | Announcement Links
    |--------------------------------------------------------------------------
    |
    | Email notifications for in-app announcements link recipients back to
    | the notification center. Override the path if the frontend route ever
    | changes or if a different host is required.
    |
    */

    'announcement_action_url' => env('NOTIFICATIONS_ANNOUNCEMENT_URL', '/announcements'),

];
