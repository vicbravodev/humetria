<?php

declare(strict_types=1);

namespace App\Modules\Assistant\Contracts;

use Carbon\CarbonImmutable;

/**
 * Whether a person, or anyone, has used Humi, for modules that guide people (Primeros pasos, the demo's guide). Never what
 * they asked, nor who.
 */
interface HumiActivity
{
    /** The person has sent Humi at least one message in the current company. Read from the usage, which survives deleting the conversations. */
    public function hasAsked(int $userId): bool;

    /** Anyone has sent Humi a message in the current company at `$since` or after. Read from the usage, like hasAsked(). */
    public function askedSince(CarbonImmutable $since): bool;
}
