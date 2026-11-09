<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Listeners;

use App\Modules\Attendance\Actions\ReopenWeeks;
use App\Modules\Attendance\Events\MarkCorrected;

/** A correction reopens the confirmed weeks it touches, inside the correction's transaction. */
final readonly class ReopenOnCorrection
{
    public function __construct(private ReopenWeeks $reopen) {}

    public function handle(MarkCorrected $event): void
    {
        $this->reopen->handle($event->markId);
    }
}
