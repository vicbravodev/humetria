<?php

declare(strict_types=1);

namespace App\Modules\Messaging\Models;

use App\Modules\Messaging\Enums\DeliveryStatus;
use Carbon\CarbonImmutable;
use Database\Factories\Messaging\DeliveryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * One message to one person by one channel. Its id is the key of idempotence. It holds the HMAC of the phone, never the phone,
 * and no body. Read across companies (webhooks, reconciliation, retention): every read inside a company names its tenant_id.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $channel
 * @property string|null $template
 * @property string|null $provider_message_id
 * @property string|null $recipient_hash
 * @property int|null $key_version
 * @property string $invitation_type
 * @property string $invitation_id
 * @property string $source_type
 * @property string $source_id
 * @property DeliveryStatus $status
 * @property CarbonImmutable|null $accepted_at
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
#[Fillable(['tenant_id', 'channel', 'template', 'provider_message_id', 'recipient_hash', 'key_version', 'invitation_type', 'invitation_id', 'source_type', 'source_id', 'status', 'accepted_at'])]
#[UseFactory(DeliveryFactory::class)]
final class Delivery extends Model
{
    /** @use HasFactory<DeliveryFactory> */
    use HasFactory, HasVersion4Uuids;

    protected $table = 'messaging_deliveries';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['status' => DeliveryStatus::class, 'accepted_at' => 'immutable_datetime'];
    }
}
