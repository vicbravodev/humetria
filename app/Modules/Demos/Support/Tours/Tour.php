<?php

declare(strict_types=1);

namespace App\Modules\Demos\Support\Tours;

use App\Modules\Demos\Data\StepState;
use App\Modules\Demos\Data\TourStep;
use App\Modules\Demos\Models\DemoSession;

/** A tour of the guided session: its steps, and a detector that tells from public contracts whether each one happened. */
interface Tour
{
    /** @return list<TourStep> */
    public function steps(): array;

    /** Computed every time from the data: nothing is kept as done (only the skipped steps, on the session). */
    public function state(TourStep $step, DemoSession $session): StepState;
}
