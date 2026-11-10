<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Enums;

/** The arrow of a trend between two waves. */
enum Direction: string
{
    case Up = 'up';
    case Down = 'down';
    case Same = 'same';
}
