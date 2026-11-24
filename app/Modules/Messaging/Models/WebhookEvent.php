<?php

declare(strict_types=1);

namespace App\Modules\Messaging\Models;

use Carbon\CarbonImmutable;
use Database\Factories\Messaging\WebhookEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A provider event already seen (deduplication), before any company is known; and, for a Meta status whose message does not
 * exist yet, what is needed to reprocess it (never the phone).
 *
 * @property string $id
 * @property string $provider
 * @property string $event_id
 * @property CarbonImmutable $received_at
 * @property array<string, mixed>|null $orphan
 * @property CarbonImmutable|null $processed_at
 */
#[Fillable(['provider', 'event_id', 'received_at', 'orphan', 'processed_at'])]
#[UseFactory(WebhookEventFactory::class)]
final class WebhookEvent extends Model
{
    /** @use HasFactory<WebhookEventFactory> */
    use HasFactory, HasVersion4Uuids;

    public $timestamps = false;

    protected $table = 'messaging_webhook_events';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['received_at' => 'immutable_datetime', 'orphan' => 'array', 'processed_at' => 'immutable_datetime'];
    }
}
