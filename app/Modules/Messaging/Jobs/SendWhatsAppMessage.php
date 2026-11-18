<?php

declare(strict_types=1);

namespace App\Modules\Messaging\Jobs;

use App\Modules\Messaging\Contracts\ChannelConsent;
use App\Modules\Messaging\Contracts\InvitationLinks;
use App\Modules\Messaging\Contracts\WhatsAppGateway;
use App\Modules\Messaging\Data\FailureCause;
use App\Modules\Messaging\Data\OutboundMessage;
use App\Modules\Messaging\Data\WhatsAppInvitation;
use App\Modules\Messaging\Enums\DeliveryStatus;
use App\Modules\Messaging\Events\WhatsAppMessageAccepted;
use App\Modules\Messaging\Exceptions\RetryableWhatsAppFailure;
use App\Modules\Messaging\Models\Delivery;
use App\Modules\Messaging\Support\DeliveryFailures;
use App\Modules\Messaging\Support\RecordDeliveryEvent;
use App\Modules\Messaging\Support\WhatsAppSwitch;
use App\Modules\Tenancy\Contracts\Companies;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Sends one WhatsApp message. It carries only the delivery id: the phone and the link token are read when it runs (InvitationLinks)
 * and never travel through the queue. The row of the message exists before Meta is called, so a retry finds it (rule 7).
 *
 * `maxExceptions` instead of `tries`, so the rate limiter and the daily cap, which release the job, do not spend attempts: only
 * three real failures do.
 */
final class SendWhatsAppMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 0;

    public int $maxExceptions = 3;

    public function __construct(public readonly string $deliveryId)
    {
        $this->onQueue('whatsapp');
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [60, 300];
    }

    public function retryUntil(): DateTimeInterface
    {
        return now()->addDays(3);
    }

    /** @return list<object> */
    public function middleware(): array
    {
        return [new RateLimited('whatsapp-send')];
    }

    public function handle(
        WhatsAppGateway $gateway,
        ChannelConsent $consent,
        WhatsAppSwitch $switch,
        Companies $companies,
        DeliveryFailures $failures,
        RecordDeliveryEvent $record,
    ): void {
        $delivery = Delivery::query()->find($this->deliveryId);

        if ($delivery === null) {
            return;
        }

        $companies->enter($delivery->tenant_id);

        // Idempotence (rule 7): a message that has an id, or that already ended or waits for a webhook, is not sent again.
        if ($delivery->provider_message_id !== null || $delivery->status !== DeliveryStatus::Created) {
            return;
        }

        if (! $switch->available() || $this->platformCapReached($switch)) {
            $failures->fail($delivery, FailureCause::Paused, DeliveryStatus::Paused);

            return;
        }

        if ($this->dailyCapReached($delivery)) {
            return;
        }

        $invitation = $this->invitation($delivery);

        if ($invitation === null) {
            $failures->fail($delivery, FailureCause::InvitationClosed);

            return;
        }

        // A STOP after the launch: the person is no longer written to through WhatsApp, and the mail takes over (rule 9).
        if (! $consent->canSend($delivery->tenant_id, 'whatsapp', $invitation->mobile)) {
            $failures->fail($delivery, FailureCause::OptedOut);

            return;
        }

        $outcome = $gateway->send(new OutboundMessage(
            $invitation->mobile,
            $invitation->template,
            config()->string('whatsapp.language'),
            $invitation->bodyParameters,
            $invitation->buttonSuffix,
            $delivery->id,
        ));

        if ($outcome->isAccepted()) {
            DB::transaction(function () use ($delivery, $outcome, $record): void {
                $delivery->update(['provider_message_id' => $outcome->providerMessageId, 'accepted_at' => now()]);
                $record->handle($delivery, DeliveryStatus::Sent, CarbonImmutable::now());
            });

            event(new WhatsAppMessageAccepted($delivery->id, $delivery->tenant_id));
            Log::info('whatsapp.message_accepted', ['delivery_id' => $delivery->id, 'template' => $invitation->template]);

            return;
        }

        $cause = $outcome->cause ?? FailureCause::UnknownError;

        // It is not known whether it left: it stays unknown, with its hold, until a status webhook or the reconciliation settles it.
        if ($cause === FailureCause::TimedOut) {
            $record->handle($delivery, DeliveryStatus::Unknown, CarbonImmutable::now(), $cause);

            return;
        }

        if ($cause->retryable()) {
            throw new RetryableWhatsAppFailure($cause->value);
        }

        $failures->fail($delivery, $cause);
    }

    /** Called when the attempts run out (or the three days): the message ends as failed and the mail takes over. */
    public function failed(Throwable $exception): void
    {
        $delivery = Delivery::query()->find($this->deliveryId);

        if ($delivery === null || $delivery->provider_message_id !== null || $delivery->status !== DeliveryStatus::Created) {
            return;
        }

        resolve(Companies::class)->enter($delivery->tenant_id);
        resolve(DeliveryFailures::class)->fail($delivery, FailureCause::RetriesExhausted);
    }

    private function platformCapReached(WhatsAppSwitch $switch): bool
    {
        $monthStart = CarbonImmutable::now(config()->string('app.display_timezone'))->startOfMonth()->utc();

        $accepted = Delivery::query()->where('channel', 'whatsapp')->where('accepted_at', '>=', $monthStart)->count();

        if ($accepted < config()->integer('whatsapp.platform_monthly_cap')) {
            return false;
        }

        $switch->pause('platform_cap');

        return true;
    }

    private function dailyCapReached(Delivery $delivery): bool
    {
        $timezone = config()->string('app.display_timezone');
        $today = CarbonImmutable::now($timezone)->startOfDay();

        $accepted = Delivery::query()
            ->where('tenant_id', $delivery->tenant_id)
            ->where('channel', 'whatsapp')
            ->where('accepted_at', '>=', $today->utc())
            ->count();

        if ($accepted < config()->integer('whatsapp.daily_cap_per_company')) {
            return false;
        }

        // Tomorrow just after midnight in the display timezone; a release does not spend an attempt.
        $this->release((int) now()->diffInSeconds($today->addDay()->addMinutes(5), false));
        Log::info('whatsapp.daily_cap_reached', ['delivery_id' => $delivery->id]);

        return true;
    }

    private function invitation(Delivery $delivery): ?WhatsAppInvitation
    {
        foreach (app()->tagged(InvitationLinks::TAG) as $links) {
            if ($links instanceof InvitationLinks && $links->type() === $delivery->invitation_type) {
                return $links->whatsApp($delivery->invitation_id);
            }
        }

        return null;
    }
}
