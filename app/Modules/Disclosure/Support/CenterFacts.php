<?php

declare(strict_types=1);

namespace App\Modules\Disclosure\Support;

/**
 * What is recorded about one work center that the board judges.
 */
final readonly class CenterFacts
{
    public function __construct(public ?PolicyFact $policy) {}
}
