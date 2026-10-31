<?php

declare(strict_types=1);

namespace App\Logging;

use Illuminate\Log\Logger;
use Monolog\Logger as Monolog;

/**
 * Log channel tap that AppServiceProvider adds to every configured channel: no channel, present or future,
 * can write a sensitive key unredacted.
 */
final class RedactEveryChannel
{
    public function __invoke(Logger $logger): void
    {
        $monolog = $logger->getLogger();

        if ($monolog instanceof Monolog) {
            $monolog->pushProcessor(new RedactSensitiveData);
        }
    }
}
