<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Minimum group
    |--------------------------------------------------------------------------
    |
    | The fewest complaints a cell of the channel's summary needs before its number is shown (criterio C-47, as C-23). Applied
    | to every cell and with complementary suppression. Never below 5.
    |
    */

    'min_group' => max(5, (int) env('COMPLAINTS_MIN_GROUP', 5)),

    /*
    |--------------------------------------------------------------------------
    | Text lengths
    |--------------------------------------------------------------------------
    */

    'description' => ['min' => 20, 'max' => 5000],
    'free_text_max' => 1000,
    'message_max' => 5000,

    /*
    |--------------------------------------------------------------------------
    | Retention and deadlines (criterios C-48 and C-49: Humetría's, not the norm's)
    |--------------------------------------------------------------------------
    */

    'retention' => ['default_months' => 24, 'min_months' => 12, 'max_months' => 60, 'notice_email_days' => 30, 'stagnant_days' => 90],
    'deadlines' => ['acknowledge_days' => 3, 'resolve_days' => 30],

    /*
    |--------------------------------------------------------------------------
    | Anti-spam without identity
    |--------------------------------------------------------------------------
    |
    | Limits are keyed by an HMAC of the address, never the address itself, and live in the limiter's cache only (Redis in
    | production). The ticket is the signed, stateless CSRF of the public routes.
    |
    */

    'rate_limits' => [
        'submissions_per_hour' => (int) env('COMPLAINTS_SUBMISSIONS_PER_HOUR', 5),
        'follow_ups_per_hour' => (int) env('COMPLAINTS_FOLLOW_UPS_PER_HOUR', 30),
        'daily_cap' => (int) env('COMPLAINTS_DAILY_CAP', 200),
    ],
    'ticket' => ['min_seconds' => 3, 'max_seconds' => 7200],

    /*
    |--------------------------------------------------------------------------
    | External alternative
    |--------------------------------------------------------------------------
    |
    | What the form offers when a channel has nobody independent to receive a complaint against HR or the management.
    |
    */

    'external_alternative' => [
        'name' => 'Dirección General de Inspección Federal del Trabajo (STPS)',
        'email' => 'inspeccionfederal@stps.gob.mx',
        'phone' => '55 3067-3000',
        'note' => 'La denuncia puede ser anónima si da la razón social y la ubicación del centro de trabajo y expone claramente las circunstancias.',
        'source' => 'STPS, Preguntas frecuentes sobre el entendimiento de la NOM-035-STPS-2018 (parte uno), pregunta 10.',
        'consulted_on' => '2026-11-03',
    ],

];
