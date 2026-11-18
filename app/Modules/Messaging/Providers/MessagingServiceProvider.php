<?php

declare(strict_types=1);

namespace App\Modules\Messaging\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

/**
 * Messaging: transport of WhatsApp and of the mail events. Its contracts are bound here as they appear.
 */
final class MessagingServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // The providers (Meta, Resend) have no account here: they are throttled per address, whatever the body says.
        RateLimiter::for('messaging-webhooks', fn (Request $request): Limit => Limit::perMinute(config()->integer('messaging.webhook_per_minute'))
            ->by('messaging-webhooks:'.$request->ip()));
    }
}
