<?php

declare(strict_types=1);

namespace App\Modules\Seating\Listeners;

use App\Modules\Seating\Actions\FlagReassessment;
use App\Modules\Seating\Events\PersonInputSubmitted;

/**
 * A person answered again about their footwear and discomfort: if it differs from what their assessment recorded, they are
 * due to be assessed again.
 */
final readonly class FlagOnPersonInput
{
    public function __construct(private FlagReassessment $flag) {}

    public function handle(PersonInputSubmitted $event): void
    {
        $this->flag->handle($event->employeeId);
    }
}
