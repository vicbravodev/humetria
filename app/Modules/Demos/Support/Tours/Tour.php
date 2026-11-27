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

    /**
     * The state of each step that waits for a fact of the data, by key, reading each contract once: nothing is kept as done
     * (only the skipped steps, on the session). A step that waits for a page visit (`TourStep::$visit`) is the catalog's.
     *
     * @return array<string, StepState>
     */
    public function states(DemoSession $session): array;
}
