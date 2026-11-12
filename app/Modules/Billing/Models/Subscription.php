<?php

declare(strict_types=1);

namespace App\Modules\Billing\Models;

use App\Modules\Billing\Enums\Plan;
use App\Modules\Billing\Enums\Profile;
use App\Modules\Billing\Enums\SubscriptionStatus;
use Carbon\CarbonImmutable;
use Database\Factories\Billing\SubscriptionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * What a company contracted: the plan, how it is priced, the catalog version it was quoted with, the people and centers it
 * pays for and when it renews. Not access (that is Tenancy's). Read across companies by the console, so it has a tenant_id
 * but no per-company scope.
 *
 * @property string $id
 * @property string $tenant_id
 * @property Plan $plan
 * @property Profile $profile
 * @property string $catalog_version
 * @property SubscriptionStatus $status
 * @property int $seats
 * @property int $centers
 * @property CarbonImmutable $starts_on
 * @property CarbonImmutable $renews_on
 * @property int|null $negotiated_price_cents
 * @property CarbonImmutable|null $negotiated_valid_until
 * @property Plan|null $pending_plan
 * @property Profile|null $pending_profile
 * @property int|null $pending_seats
 * @property int|null $pending_centers
 * @property CarbonImmutable|null $pending_effective_on
 */
#[Fillable(['tenant_id', 'plan', 'profile', 'catalog_version', 'status', 'seats', 'centers', 'starts_on', 'renews_on', 'negotiated_price_cents', 'negotiated_valid_until', 'pending_plan', 'pending_profile', 'pending_seats', 'pending_centers', 'pending_effective_on'])]
#[UseFactory(SubscriptionFactory::class)]
final class Subscription extends Model
{
    /** @use HasFactory<SubscriptionFactory> */
    use HasFactory, HasVersion4Uuids;

    /**
     * The negotiated price if it is in force on that day: it has no end date, or the day is not after it.
     */
    public function negotiatedPriceOn(CarbonImmutable $day): ?int
    {
        if ($this->negotiated_price_cents === null) {
            return null;
        }

        return $this->negotiated_valid_until === null || $day->lessThanOrEqualTo($this->negotiated_valid_until) ? $this->negotiated_price_cents : null;
    }

    /**
     * @return HasMany<SubscriptionCharge, $this>
     */
    public function charges(): HasMany
    {
        return $this->hasMany(SubscriptionCharge::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'plan' => Plan::class,
            'profile' => Profile::class,
            'status' => SubscriptionStatus::class,
            'starts_on' => 'immutable_date',
            'renews_on' => 'immutable_date',
            'negotiated_valid_until' => 'immutable_date',
            'pending_plan' => Plan::class,
            'pending_profile' => Profile::class,
            'pending_effective_on' => 'immutable_date',
        ];
    }
}
