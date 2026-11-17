<?php

declare(strict_types=1);

namespace App\Modules\Demos\Contracts;

/** The demo people kept for the door: the story leaves everything of theirs pending, for a visitor to answer live. */
interface DemoDoorPool
{
    /**
     * Their employee ids, in the order the door hands them out (the same on every call).
     *
     * @return list<string>
     */
    public function employeeIds(): array;
}
