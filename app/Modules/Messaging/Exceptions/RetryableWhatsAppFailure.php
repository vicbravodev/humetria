<?php

declare(strict_types=1);

namespace App\Modules\Messaging\Exceptions;

use RuntimeException;

/** A send failed for a cause worth another attempt (a rate limit, a provider error): the job retries with its backoff. */
final class RetryableWhatsAppFailure extends RuntimeException {}
