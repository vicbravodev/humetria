<?php

declare(strict_types=1);

namespace App\Modules\Leave\Enums;

/**
 * Why days enter or leave a balance. Every change of a balance is one entry of the ledger with one of these kinds.
 */
enum EntryKind: string
{
    /** The days of a period, on its anniversary. */
    case Granted = 'granted';
    /** What was left of a period from before the company started using Vacaciones (criterio C-21). */
    case Opening = 'opening';
    /** The days of an approved request. */
    case Taken = 'taken';
    /** A correction by RH, with its note. */
    case Adjustment = 'adjustment';
    /** Extra days that lapsed by the policy's own rule (criterio C-14); never statutory ones. */
    case Expired = 'expired';
    /** What was pending when the relationship ended (criterio C-07). */
    case Termination = 'termination';
}
