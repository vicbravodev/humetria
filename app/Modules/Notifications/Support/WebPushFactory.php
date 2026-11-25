<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Support;

use App\Modules\Notifications\Actions\SendPush;
use GuzzleHttp\Psr7\HttpFactory;
use Minishlink\WebPush\WebPush;
use Psr\Log\NullLogger;

/**
 * Builds the push library with everything explicit: the HTTP client of Laravel and the PSR-17 factories of guzzle/psr7, so nothing is
 * discovered; and a `NullLogger`, because without one a missing `gmp` or `bcmath` is a `trigger_error` that Laravel turns into an exception.
 */
final readonly class WebPushFactory
{
    public function make(VapidConfig $vapid): WebPush
    {
        $factory = new HttpFactory;

        return new WebPush($vapid->auth(), ['TTL' => SendPush::TTL, 'urgency' => 'normal'], new LaravelHttpClient, $factory, $factory, null, new NullLogger);
    }
}
