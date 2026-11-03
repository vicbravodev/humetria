<?php

declare(strict_types=1);

namespace App\Modules\Seating\Support;

use Carbon\CarbonImmutable;

/**
 * Today's date as the company lives it (America/Mexico_City): what a declaration, an assignment or an agreement is dated.
 */
final class Today
{
    public static function date(): string
    {
        return CarbonImmutable::now(config()->string('app.display_timezone'))->toDateString();
    }
}
