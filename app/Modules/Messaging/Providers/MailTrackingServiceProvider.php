<?php

declare(strict_types=1);

namespace App\Modules\Messaging\Providers;

use App\Modules\Messaging\Listeners\OpenMailDelivery;
use App\Modules\Messaging\Listeners\StampMailDelivery;
use App\Modules\Messaging\Support\MailDeliveryStash;
use App\Modules\Messaging\Support\ResendApiTransport;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Notifications\Events\NotificationSending;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\ServiceProvider;

/**
 * The history of the mail: each followed mail gets its row and leaves stamped with its id. Apart from MessagingServiceProvider so
 * the two lines of work do not edit one file. It also takes over the `resend` mail transport: ours, over the API, because only
 * there does Resend return something of ours in its events.
 */
final class MailTrackingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(MailDeliveryStash::class);
    }

    public function boot(): void
    {
        Event::listen(NotificationSending::class, OpenMailDelivery::class);
        Event::listen(MessageSending::class, StampMailDelivery::class);

        Mail::extend('resend', fn (array $config): ResendApiTransport => new ResendApiTransport(
            is_string($config['key'] ?? null) ? $config['key'] : config()->string('services.resend.key'),
        ));
    }
}
