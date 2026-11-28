<?php

declare(strict_types=1);

namespace App\Modules\Demos\Data;

/** The requests nobody has answered: all the new ones, and those of them older than 48 hours. Only counts. */
final readonly class DemoWaiting
{
    public function __construct(public int $new, public int $unattended) {}
}
