<?php

declare(strict_types=1);

namespace App\Modules\Assistant\Contracts;

/**
 * Whether a person has used Humi, for modules that guide people (Primeros pasos). Never what they asked.
 */
interface HumiActivity
{
    /** The person has sent Humi at least one message in the current company. Read from the usage, which survives deleting the conversations. */
    public function hasAsked(int $userId): bool;
}
