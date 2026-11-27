<?php

declare(strict_types=1);

namespace App\Modules\Leave\Data;

/** The requests made since a moment and, of them, the ones approved (taken ones too: they were approved first). */
final readonly class LeaveCounts
{
    public function __construct(
        public int $requested,
        public int $approved,
    ) {}
}
