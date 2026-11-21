<?php

declare(strict_types=1);

namespace App\Modules\Messaging\Actions;

use App\Modules\Messaging\Enums\DeliveryStatus;
use App\Modules\Messaging\Models\Delivery;
use App\Modules\Messaging\Support\RecordDeliveryEvent;
use App\Modules\Messaging\Support\WebhookInbox;
use Carbon\CarbonImmutable;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * One event of Resend about a mail message. The message is found by the `delivery_id` tag we put on it when we sent it (Resend
 * returns tags, not headers, and replaces the Message-ID), so a message it does not know, one of WhatsApp, or an event with no
 * tag, is ignored. It never reads or logs an address, a subject or the payload. A bounce never starts anything on WhatsApp: mail
 * has no fallback (rule 9).
 */
final class HandleResendEvents
{
    public const string TAG = 'delivery_id';

    public function __construct(private readonly WebhookInbox $inbox, private readonly RecordDeliveryEvent $record) {}

    /**
     * @param  array<mixed>  $payload
     */
    public function handle(string $svixId, array $payload): void
    {
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];
        $status = $this->status($payload['type'] ?? null, $data);

        if ($status === null) {
            return;
        }

        // The id of the event and its effect are one transaction: if applying it fails, the retry of Resend is processed.
        DB::transaction(function () use ($svixId, $data, $payload, $status): void {
            if ($this->inbox->firstTime('resend', $svixId)) {
                $this->apply($data, $payload, $status);
            }
        });
    }

    /**
     * @param  array<mixed>  $data
     * @param  array<mixed>  $payload
     */
    private function apply(array $data, array $payload, DeliveryStatus $status): void
    {
        $tags = is_array($data['tags'] ?? null) ? $data['tags'] : [];
        $id = $tags[self::TAG] ?? null;
        $delivery = is_string($id) && Str::isUuid($id) ? Delivery::query()->where('channel', 'mail')->find($id) : null;

        if ($delivery === null) {
            return;
        }

        if ($delivery->provider_message_id === null && is_string($data['email_id'] ?? null)) {
            $delivery->update(['provider_message_id' => $data['email_id']]);
        }

        $this->record->handle($delivery, $status, $this->occurredAt($payload['created_at'] ?? null));
        Log::info('mail.event_applied', ['delivery_id' => $delivery->id, 'status' => $status->value]);
    }

    /**
     * @param  array<mixed>  $data
     */
    private function status(mixed $type, array $data): ?DeliveryStatus
    {
        $bounce = is_array($data['bounce'] ?? null) ? $data['bounce'] : [];

        return match ($type) {
            'email.sent' => DeliveryStatus::Sent,
            'email.delivered' => DeliveryStatus::Delivered,
            'email.delivery_delayed' => DeliveryStatus::Delayed,
            'email.complained' => DeliveryStatus::Complained,
            // Only a bounce Resend calls temporary may still arrive; a missing or unknown type is read as definitive.
            'email.bounced' => in_array($bounce['type'] ?? null, ['Temporary', 'Transient'], true) ? DeliveryStatus::Delayed : DeliveryStatus::Bounced,
            default => null,
        };
    }

    private function occurredAt(mixed $createdAt): CarbonImmutable
    {
        try {
            return is_string($createdAt) ? CarbonImmutable::parse($createdAt) : CarbonImmutable::now();
        } catch (InvalidFormatException) {
            return CarbonImmutable::now();
        }
    }
}
