<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Survey invitations
    |--------------------------------------------------------------------------
    |
    | How many survey emails go out per minute, across every company: the mail provider's limit. The jobs that send
    | them wait for the next minute once it is reached.
    |
    */

    'invitations_per_minute' => (int) env('CAMPAIGN_INVITATIONS_PER_MINUTE', 60),

    /*
    |--------------------------------------------------------------------------
    | Reminders
    |--------------------------------------------------------------------------
    |
    | The hours that must pass between two reminders to the people with a guide pending, per evaluation: a second one a few
    | minutes later only annoys them.
    |
    */

    'resend_cooldown_hours' => (int) env('CAMPAIGN_RESEND_COOLDOWN_HOURS', 12),

];
