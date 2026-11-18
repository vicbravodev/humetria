<?php

declare(strict_types=1);

namespace App\Modules\Sso\Exceptions;

use RuntimeException;

/**
 * A sign-in that cannot go on. The person is only ever told one generic thing; the reason code is for the log.
 */
final class SsoSignInFailed extends RuntimeException
{
    public function __construct(public readonly string $reason)
    {
        parent::__construct("SSO sign-in failed: {$reason}");
    }
}
