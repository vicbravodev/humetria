<?php

declare(strict_types=1);

namespace App\Modules\Leave\Exceptions;

use RuntimeException;

/**
 * A request that cannot be made or approved, with the reason as a key: each screen says it in its own words.
 */
final class RequestRefused extends RuntimeException
{
    public function __construct(public readonly string $reason)
    {
        parent::__construct("The request was refused: {$reason}.");
    }
}
