<?php

declare(strict_types=1);

namespace App\Modules\Leave\Contracts;

/**
 * How many vacation requests of the current company RH has to answer (those of «Por atender»), for the panel that puts what is urgent first.
 * A count, never who asked nor why.
 */
interface PendingRequests
{
    /** Whoever asks says whether the company uses Vacaciones: this only counts. */
    public function waiting(): int;
}
