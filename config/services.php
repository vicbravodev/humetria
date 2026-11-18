<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
        // The signing secret of the Resend webhook (Svix); during a rotation both are accepted.
        'webhook_secrets' => array_values(array_filter([env('RESEND_WEBHOOK_SECRET'), env('RESEND_WEBHOOK_SECRET_PREVIOUS')])),
        'webhook_tolerance_seconds' => 300,
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    /*
    | DB-IP's free city database (CC BY 4.0), downloaded each month by `geoip:update`: the city an account email names.
    */

    'geoip' => [
        'database' => env('GEOIP_DATABASE', storage_path('app/private/geoip/dbip-city-lite.mmdb')),
    ],

    /*
    | PostHog (US cloud) on the landing page only. No project token, no block in the document, no PostHog origin in the CSP.
    | Consent is `denied` until the owner's lawyer says otherwise (see App\Http\Consent): with it, nothing is measured.
    | The host is not configurable on purpose: it is one constant of App\Http\Analytics, the one the CSP opens.
    */

    // The offline import of demo requests into Google Ads: the name must be the conversion action's, letter by letter.
    'google_ads' => [
        'conversion_name' => env('GOOGLE_ADS_CONVERSION_NAME', 'Humetría · Solicitud de demo'),
    ],

    'posthog' => [
        'project_token' => env('POSTHOG_PROJECT_TOKEN', ''),
        'consent_default' => env('POSTHOG_CONSENT_DEFAULT', 'denied'),
        // Anonymous, masked recording of a company's panel (resources/js/lib/session-recording.ts). Off unless switched on.
        'session_recording' => (bool) env('POSTHOG_SESSION_RECORDING', false),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
