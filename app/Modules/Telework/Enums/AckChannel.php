<?php

declare(strict_types=1);

namespace App\Modules\Telework\Enums;

/** How an acknowledgement was given: by the person in their portal, on paper recorded by RH, or by a Comisión member in the panel. */
enum AckChannel: string
{
    case Portal = 'portal';
    case Manual = 'manual';
    case Panel = 'panel';
}
