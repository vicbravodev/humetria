<?php

declare(strict_types=1);

namespace App\Modules\Messaging\Support;

use App\Modules\Messaging\Enums\DeliveryStatus;
use Carbon\CarbonImmutable;

/**
 * One status Meta reports for a message (`statuses[]` of the `messages` field), reduced to what is used. Never the recipient.
 */
final readonly class MetaStatus
{
    private const array KNOWN = ['sent', 'delivered', 'read', 'failed'];

    public function __construct(
        public string $providerMessageId,
        public ?string $callbackId,
        public string $status,
        public int $timestamp,
        public ?int $errorCode,
    ) {}

    /** @param array<mixed> $status one element of `statuses` */
    public static function fromPayload(array $status): ?self
    {
        $id = $status['id'] ?? null;
        $name = $status['status'] ?? null;

        if (! is_string($id) || $id === '' || ! is_string($name) || ! in_array($name, self::KNOWN, true)) {
            return null;
        }

        $callback = $status['biz_opaque_callback_data'] ?? null;
        $errors = $status['errors'] ?? null;
        $first = is_array($errors) ? ($errors[0] ?? null) : null;
        $code = is_array($first) ? ($first['code'] ?? null) : null;
        $timestamp = $status['timestamp'] ?? null;

        return new self(
            $id,
            is_string($callback) && $callback !== '' ? $callback : null,
            $name,
            is_numeric($timestamp) ? (int) $timestamp : CarbonImmutable::now()->getTimestamp(),
            is_int($code) ? $code : null,
        );
    }

    /** @param array<mixed> $orphan what toOrphan() wrote */
    public static function fromOrphan(array $orphan): ?self
    {
        $code = $orphan['error_code'] ?? null;

        return self::fromPayload([
            'id' => $orphan['provider_message_id'] ?? null,
            'status' => $orphan['status'] ?? null,
            'timestamp' => $orphan['timestamp'] ?? null,
            'biz_opaque_callback_data' => $orphan['callback_id'] ?? null,
            'errors' => is_int($code) ? [['code' => $code]] : null,
        ]);
    }

    /** @return array<string, mixed> what is kept to reprocess a status whose message does not exist yet */
    public function toOrphan(): array
    {
        return ['provider_message_id' => $this->providerMessageId, 'callback_id' => $this->callbackId, 'status' => $this->status, 'timestamp' => $this->timestamp, 'error_code' => $this->errorCode];
    }

    public function eventId(): string
    {
        return "status:{$this->providerMessageId}:{$this->status}:{$this->timestamp}";
    }

    public function deliveryStatus(): DeliveryStatus
    {
        return match ($this->status) {
            'sent' => DeliveryStatus::Sent,
            'delivered' => DeliveryStatus::Delivered,
            'read' => DeliveryStatus::Read,
            default => DeliveryStatus::Failed,
        };
    }
}
