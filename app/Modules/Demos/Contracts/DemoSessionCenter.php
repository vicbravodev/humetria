<?php

declare(strict_types=1);

namespace App\Modules\Demos\Contracts;

/** The work center of the demo session in course: the room where the audience is, where the demo bends the norm's sizes. */
interface DemoSessionCenter
{
    /** Whether the request works in the demo and `$workCenterId` is its open session's center. */
    public function is(string $workCenterId): bool;
}
