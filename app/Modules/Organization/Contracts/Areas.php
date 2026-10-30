<?php

declare(strict_types=1);

namespace App\Modules\Organization\Contracts;

/**
 * The areas of the current company, as other modules read them.
 */
interface Areas
{
    /** An area's name; null outside the company. */
    public function name(string $areaId): ?string;
}
