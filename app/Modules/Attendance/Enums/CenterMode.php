<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Enums;

/**
 * How the people of a work center mark (criterio 4.5): as the company's policy says (zones and QR), or in the field, from
 * their phone wherever they are, keeping the position with no zone to restrict it.
 */
enum CenterMode: string
{
    case Company = 'company';
    case Field = 'field';
}
