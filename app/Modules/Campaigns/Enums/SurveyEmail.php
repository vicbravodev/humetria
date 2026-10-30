<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Enums;

/**
 * Which email carries a person's survey link: the first one of an evaluation, a reminder to whoever already got a
 * link, or the request for Guía I after a traumatic event (first or again, it says the same).
 */
enum SurveyEmail: string
{
    case Invitation = 'invitation';
    case Reminder = 'reminder';
    case Trauma = 'trauma';
}
