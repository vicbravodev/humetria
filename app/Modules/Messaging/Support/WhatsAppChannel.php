<?php

declare(strict_types=1);

namespace App\Modules\Messaging\Support;

use App\Modules\Messaging\Actions\CreateDelivery;
use App\Modules\Messaging\Contracts\ChannelConsent;
use App\Modules\Messaging\Contracts\TracksDelivery;
use App\Modules\Messaging\Jobs\SendWhatsAppMessage;
use App\Modules\Tenancy\Contracts\CurrentTenant;
use App\Support\PhoneNumber;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use LogicException;

/**
 * The `whatsapp` notification channel. It does not call Meta: it checks consent, writes the message row and queues the job,
 * which carries only the row's id. The notification must say which invitation it carries (TracksDelivery).
 */
final class WhatsAppChannel
{
    public function __construct(
        private readonly ChannelConsent $consent,
        private readonly CurrentTenant $tenant,
        private readonly CreateDelivery $create,
        private readonly TemplateRegistry $templates,
    ) {}

    public function send(object $notifiable, Notification $notification): void
    {
        $reference = $notification instanceof TracksDelivery ? $notification->deliveryReference() : null;

        if ($reference === null) {
            throw new LogicException('A WhatsApp notification must implement TracksDelivery and name its invitation.');
        }

        $route = method_exists($notifiable, 'routeNotificationFor') ? $notifiable->routeNotificationFor('whatsapp', $notification) : null;
        $e164 = is_string($route) ? PhoneNumber::e164($route) : null;

        if ($e164 === null || ! $this->consent->canSend($this->tenant->id(), 'whatsapp', $e164)) {
            Log::info('whatsapp.send_refused', ['invitation_type' => $reference->type]);

            return;
        }

        $delivery = $this->create->handle('whatsapp', $reference, $this->templates->forInvitation($reference->type)->key, $e164);

        DB::afterCommit(fn () => SendWhatsAppMessage::dispatch($delivery->id));
    }
}
