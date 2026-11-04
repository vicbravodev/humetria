<?php

declare(strict_types=1);

namespace App\Modules\Compensation\Data;

/**
 * What a point of salary is worth in a negotiation, as first-year costs in cents (with the retroactive months): `onePointCents`
 * is a raise of 1 % on an unchanged contract; `baseCents` the proposal; `minusOneCents` and `plusOneCents` the proposal with
 * every raise a point lower (never below zero) and a point higher.
 */
final readonly class SensitivityResult
{
    public function __construct(public int $onePointCents, public int $baseCents, public int $minusOneCents, public int $plusOneCents) {}
}
