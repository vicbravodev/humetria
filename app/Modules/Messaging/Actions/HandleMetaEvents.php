<?php

declare(strict_types=1);

namespace App\Modules\Messaging\Actions;

use App\Modules\Messaging\Data\FailureCause;
use App\Modules\Messaging\Enums\DeliveryStatus;
use App\Modules\Messaging\Events\WhatsAppMessageAccepted;
use App\Modules\Messaging\Models\Delivery;
use App\Modules\Messaging\Support\DeliveryFailures;
use App\Modules\Messaging\Support\MetaStatus;
use App\Modules\Messaging\Support\PhoneForMeta;
use App\Modules\Messaging\Support\RecordDeliveryEvent;
use App\Modules\Messaging\Support\WebhookInbox;
use App\Modules\Messaging\Support\WhatsAppErrorMap;
use App\Modules\Tenancy\Contracts\Companies;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * What a signed webhook of Meta says. Statuses go to the history of their message (in its company, whoever is current); a STOP
 * becomes an opt-out; the quality of the number is logged. Anything else is ignored, and the payload itself is never logged.
 */
final class HandleMetaEvents
{
    private const array STOP_WORDS = ['STOP', 'BAJA', 'ALTO', 'CANCELAR'];

    public function __construct(
        private readonly WebhookInbox $inbox,
        private readonly Companies $companies,
        private readonly RecordDeliveryEvent $record,
        private readonly DeliveryFailures $failures,
        private readonly WhatsAppErrorMap $errors,
        private readonly RecordOptOut $optOut,
        private readonly PhoneForMeta $phones,
    ) {}

    /** @param array<mixed> $payload */
    public function handle(array $payload): void
    {
        $entries = $payload['entry'] ?? null;

        foreach (is_array($entries) ? $entries : [] as $entry) {
            $changes = is_array($entry) ? ($entry['changes'] ?? null) : null;

            foreach (is_array($changes) ? $changes : [] as $change) {
                if (is_array($change) && is_array($change['value'] ?? null)) {
                    $this->change($change['field'] ?? null, $change['value']);
                }
            }
        }
    }

    /** The message a status is about: by Meta's id, or by the id of ours it carried back (a message the job could not finish). */
    public function find(MetaStatus $status): ?Delivery
    {
        $byId = Delivery::query()->where('channel', 'whatsapp')->where('provider_message_id', $status->providerMessageId)->first();

        if ($byId !== null || $status->callbackId === null || ! Str::isUuid($status->callbackId)) {
            return $byId;
        }

        return Delivery::query()->where('channel', 'whatsapp')->whereKey($status->callbackId)->first();
    }

    /** Applies a status to its message, in its company. Used by the webhook and by the reprocessing of orphans. */
    public function apply(Delivery $delivery, MetaStatus $status): void
    {
        $this->companies->enter($delivery->tenant_id);

        $new = $status->deliveryStatus();
        $occurredAt = CarbonImmutable::createFromTimestampUTC($status->timestamp);
        $wasTerminal = $delivery->status->isTerminal();
        $settlesAcceptance = false;

        // A message the job could not finish (unknown) or whose id it did not get to keep: Meta's own id is kept now.
        if ($delivery->provider_message_id === null) {
            $accepted = $new !== DeliveryStatus::Failed;
            $delivery->update(['provider_message_id' => $status->providerMessageId, 'accepted_at' => $accepted ? $occurredAt : null]);
            $settlesAcceptance = $accepted;
        }

        if ($new === DeliveryStatus::Failed) {
            $cause = $status->errorCode === null ? FailureCause::UnknownError : $this->errors->fromCode($status->errorCode);
            $this->failures->fail($delivery, $cause, DeliveryStatus::Failed, $occurredAt, announce: ! $wasTerminal);

            return;
        }

        $this->record->handle($delivery, $new, $occurredAt);

        if ($settlesAcceptance) {
            event(new WhatsAppMessageAccepted($delivery->id, $delivery->tenant_id));
        }
    }

    /** @param array<mixed> $value */
    private function change(mixed $field, array $value): void
    {
        match ($field) {
            'messages' => $this->messages($value),
            'phone_number_quality_update' => $this->quality($value),
            default => null,
        };
    }

    /** @param array<mixed> $value */
    private function messages(array $value): void
    {
        $statuses = $value['statuses'] ?? null;

        foreach (is_array($statuses) ? $statuses : [] as $raw) {
            $status = is_array($raw) ? MetaStatus::fromPayload($raw) : null;

            if ($status !== null) {
                $this->status($status);
            }
        }

        $messages = $value['messages'] ?? null;

        foreach (is_array($messages) ? $messages : [] as $message) {
            if (is_array($message)) {
                $this->inbound($message);
            }
        }
    }

    private function status(MetaStatus $status): void
    {
        // The id of the event and its effect are one transaction: if applying it fails, the id is not kept and the retry of Meta is processed.
        DB::transaction(function () use ($status): void {
            $delivery = $this->find($status);

            if ($this->inbox->firstTime('meta', $status->eventId(), $delivery === null ? $status->toOrphan() : null) && $delivery !== null) {
                $this->apply($delivery, $status);
            }
        });
    }

    /** @param array<mixed> $message */
    private function inbound(array $message): void
    {
        $id = $message['id'] ?? null;
        $from = $message['from'] ?? null;
        $text = is_array($message['text'] ?? null) ? ($message['text']['body'] ?? null) : null;

        if (($message['type'] ?? null) !== 'text' || ! is_string($id) || ! is_string($from) || ! is_string($text) || ! $this->isStop($text)) {
            return;
        }

        $phone = $this->phones->fromWaId($from);

        // Only a STOP is remembered, and only by its id: any other message leaves no trace. The id and the opt-out are one
        // transaction: a STOP that fails halfway is not taken for a repeat when Meta sends it again.
        if ($phone !== null) {
            DB::transaction(function () use ($id, $phone): void {
                if ($this->inbox->firstTime('meta', "message:{$id}")) {
                    $this->optOut->handle($phone, 'stop');
                }
            });
        }
    }

    private function isStop(string $text): bool
    {
        return in_array(trim(rtrim(mb_strtoupper(trim($text)), '.!')), self::STOP_WORDS, true);
    }

    /** @param array<mixed> $value */
    private function quality(array $value): void
    {
        $event = $value['event'] ?? null;
        $limit = $value['current_limit'] ?? null;

        Log::warning('whatsapp.quality_changed', ['event' => is_string($event) ? $event : null, 'current_limit' => is_string($limit) ? $limit : null]);
    }
}
