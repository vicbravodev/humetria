<?php

declare(strict_types=1);

namespace App\Modules\Messaging\Models;

use App\Modules\Messaging\Data\FailureCause;
use App\Modules\Messaging\Enums\DeliveryStatus;
use Carbon\CarbonImmutable;
use Database\Factories\Messaging\DeliveryEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * One change of status, with the provider's exact time. Insert-only. Only an aggregating Action reads these rows: no page
 * or Inertia prop ever carries one.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $delivery_id
 * @property DeliveryStatus $status
 * @property FailureCause|null $failure_cause
 * @property CarbonImmutable $occurred_at
 * @property CarbonImmutable $created_at
 */
#[Fillable(['tenant_id', 'delivery_id', 'status', 'failure_cause', 'occurred_at'])]
#[UseFactory(DeliveryEventFactory::class)]
final class DeliveryEvent extends Model
{
    /** @use HasFactory<DeliveryEventFactory> */
    use HasFactory, HasVersion4Uuids;

    public const ?string UPDATED_AT = null;

    protected $table = 'messaging_delivery_events';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => DeliveryStatus::class,
            'failure_cause' => FailureCause::class,
            'occurred_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
        ];
    }
}
