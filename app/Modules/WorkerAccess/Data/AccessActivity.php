<?php

declare(strict_types=1);

namespace App\Modules\WorkerAccess\Data;

/** Since a moment: the people RH sent a link to (once each, however many links) and the keys created and still in use. */
final readonly class AccessActivity
{
    public function __construct(
        public int $invited,
        public int $keys,
    ) {}
}
