<?php

declare(strict_types=1);

namespace App\Modules\Billing\Models;

use App\Modules\Billing\Enums\ChargeKind;
use Carbon\CarbonImmutable;
use Database\Factories\Billing\SubscriptionChargeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An extra charge on a company, approved by a person: recurring (accompaniment) or once (implementation, a CCT study).
 * Read across companies by the console, so it has a tenant_id but no per-company scope.
 *
 * @property string $id
 * @property string $subscription_id
 * @property string $tenant_id
 * @property ChargeKind $kind
 * @property string $description
 * @property string $scope
 * @property int $amount_cents
 * @property CarbonImmutable $charge_on
 * @property CarbonImmutable|null $ends_on
 * @property int $approved_by
 */
#[Fillable(['subscription_id', 'tenant_id', 'kind', 'description', 'scope', 'amount_cents', 'charge_on', 'ends_on', 'approved_by'])]
#[UseFactory(SubscriptionChargeFactory::class)]
final class SubscriptionCharge extends Model
{
    /** @use HasFactory<SubscriptionChargeFactory> */
    use HasFactory, HasVersion4Uuids;

    /**
     * @return BelongsTo<Subscription, $this>
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => ChargeKind::class,
            'charge_on' => 'immutable_date',
            'ends_on' => 'immutable_date',
        ];
    }
}
