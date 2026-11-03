<?php

declare(strict_types=1);

namespace App\Modules\Complaints\Support;

/** Where the references of new complaints come from; a test swaps it to force a collision. */
interface ReferenceSource
{
    public function next(): string;
}
