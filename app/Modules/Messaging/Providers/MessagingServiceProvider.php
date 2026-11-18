<?php

declare(strict_types=1);

namespace App\Modules\Messaging\Providers;

use App\Modules\Messaging\Contracts\WhatsAppGateway;
use App\Modules\Messaging\Support\NullWhatsAppGateway;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use LogicException;

/**
 * Messaging: transport of WhatsApp and of the mail events. Its contracts are bound here as they appear.
 */
final class MessagingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // The Cloud API gateway is bound by the commit that writes it; until then the function exists only as the null one.
        if (! config()->boolean('whatsapp.enabled')) {
            $this->app->bind(WhatsAppGateway::class, NullWhatsAppGateway::class);
        }
    }

    public function boot(): void
    {
        // A recipient hash needs a key: a deployment with the function on and no key would store nothing it can compare.
        if (config()->boolean('whatsapp.enabled') && config()->array('messaging.recipient_keys') === []) {
            throw new LogicException('WHATSAPP_ENABLED needs MESSAGING_RECIPIENT_KEYS (1:<base64>,2:<base64>).');
        }

        // The providers (Meta, Resend) have no account here: they are throttled per address, whatever the body says.
        RateLimiter::for('messaging-webhooks', fn (Request $request): Limit => Limit::perMinute(config()->integer('messaging.webhook_per_minute'))
            ->by('messaging-webhooks:'.$request->ip()));
    }
}
