<?php

declare(strict_types=1);

namespace App\Modules\Messaging\Actions;

use App\Modules\Messaging\Data\InvitationReference;
use App\Modules\Messaging\Enums\DeliveryStatus;
use App\Modules\Messaging\Models\Delivery;
use App\Modules\Messaging\Support\RecipientHash;
use App\Modules\Messaging\Support\RecordDeliveryEvent;
use App\Modules\Tenancy\Contracts\CurrentTenant;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;

/**
 * The row of a message, made BEFORE the provider is called, so a retried job finds it (idempotence). In the current company, in
 * `created`, with its first event. It keeps the hash of the phone (WhatsApp), never the phone.
 */
final class CreateDelivery
{
    public function __construct(
        private readonly CurrentTenant $tenant,
        private readonly RecipientHash $hashes,
        private readonly RecordDeliveryEvent $record,
    ) {}

    public function handle(string $channel, InvitationReference $invitation, ?string $template, ?string $e164): Delivery
    {
        $hash = $e164 === null ? null : $this->hashes->current($e164);

        $delivery = Delivery::query()->create([
            'tenant_id' => $this->tenant->id(),
            'channel' => $channel,
            'template' => $template,
            'recipient_hash' => $hash['hash'] ?? null,
            'key_version' => $hash['version'] ?? null,
            'invitation_type' => $invitation->type,
            'invitation_id' => $invitation->id,
            'source_type' => $invitation->sourceType,
            'source_id' => $invitation->sourceId,
            'status' => DeliveryStatus::Created,
        ]);

        $this->record->handle($delivery, DeliveryStatus::Created, CarbonImmutable::now());

        Log::info('messaging.delivery_created', ['delivery_id' => $delivery->id, 'channel' => $channel]);

        return $delivery;
    }
}
