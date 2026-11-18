<?php

declare(strict_types=1);

namespace App\Modules\Messaging\Providers;

use App\Modules\Messaging\Contracts\ChannelConsent;
use App\Modules\Messaging\Contracts\ChannelPicker;
use App\Modules\Messaging\Contracts\DeliverySummary;
use App\Modules\Messaging\Contracts\WhatsAppGateway;
use App\Modules\Messaging\Support\ChannelDeliveryTallies;
use App\Modules\Messaging\Support\ChannelRules;
use App\Modules\Messaging\Support\CloudApiWhatsAppGateway;
use App\Modules\Messaging\Support\NullWhatsAppGateway;
use App\Modules\Messaging\Support\WhatsAppChannel;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
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
        $this->app->scoped(ChannelRules::class);
        $this->app->bind(DeliverySummary::class, ChannelDeliveryTallies::class);
        $this->app->bind(ChannelConsent::class, fn (Application $app): ChannelRules => $app->make(ChannelRules::class));
        $this->app->bind(ChannelPicker::class, fn (Application $app): ChannelRules => $app->make(ChannelRules::class));
        $this->app->bind(WhatsAppGateway::class, config()->boolean('whatsapp.enabled') ? CloudApiWhatsAppGateway::class : NullWhatsAppGateway::class);
    }

    public function boot(): void
    {
        // A recipient hash needs a key: a deployment with the function on and no key would store nothing it can compare.
        if (config()->boolean('whatsapp.enabled') && config()->array('messaging.recipient_keys') === []) {
            throw new LogicException('WHATSAPP_ENABLED needs MESSAGING_RECIPIENT_KEYS (1:<base64>,2:<base64>).');
        }

        Notification::extend('whatsapp', fn (Application $app): WhatsAppChannel => $app->make(WhatsAppChannel::class));

        // Sends a second, against the number's limit: the job releases itself when the limit is hit, without spending an attempt.
        RateLimiter::for('whatsapp-send', fn (): Limit => Limit::perSecond(config()->integer('whatsapp.max_per_second')));

        // The providers (Meta, Resend) have no account here: they are throttled per address, whatever the body says.
        RateLimiter::for('messaging-webhooks', fn (Request $request): Limit => Limit::perMinute(config()->integer('messaging.webhook_per_minute'))
            ->by('messaging-webhooks:'.$request->ip()));
    }
}
