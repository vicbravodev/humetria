<?php

declare(strict_types=1);

namespace App\Modules\FirstSteps\Contracts;

use App\Modules\FirstSteps\Data\FirstStepsData;

/**
 * The guided tour of the current company for one person: what the panel shows them, or nothing.
 */
interface FirstSteps
{
    /**
     * The steps for the person, or null when they are not shown: the switch is off, the person is not the company's owner,
     * the company is read only, blocked or a union, or the tour was finished. Finishing is recorded here: when every step is
     * done, the owner who was following them gets the finished steps once, anyone else none at all. Someone working as the
     * owner (`$impersonated`) changes nothing of theirs: what would be recorded is only returned.
     */
    public function forUser(int $userId, bool $impersonated = false): ?FirstStepsData;
}
