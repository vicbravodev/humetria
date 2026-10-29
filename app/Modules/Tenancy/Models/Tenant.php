<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Models;

use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Enums\TenantStatus;
use Carbon\CarbonImmutable;
use Database\Factories\Tenancy\TenantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A company that uses the platform. Its status follows its trial and paid dates.
 *
 * @property string $id
 * @property string $legal_name
 * @property string $trade_name
 * @property string $rfc
 * @property string $slug
 * @property CarbonImmutable|null $trial_started_at
 * @property CarbonImmutable|null $active_until
 * @property CarbonImmutable|null $deactivated_at
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
#[Fillable(['legal_name', 'trade_name', 'rfc', 'slug', 'trial_started_at', 'active_until', 'deactivated_at'])] // @pest-mutate-ignore: RemoveArrayItem
#[UseFactory(TenantFactory::class)]
final class Tenant extends Model
{
    /** @use HasFactory<TenantFactory> */
    use HasFactory, HasVersion4Uuids;

    public const int TRIAL_DAYS = 14;

    public function status(): TenantStatus
    {
        if ($this->deactivated_at !== null) {
            return TenantStatus::Deactivated;
        }

        if ($this->active_until !== null) {
            return now()->lessThan($this->active_until) ? TenantStatus::Active : TenantStatus::Expired;
        }

        $trialEndsAt = $this->trialEndsAt();

        if ($trialEndsAt === null) {
            return TenantStatus::NotStarted;
        }

        return now()->lessThan($trialEndsAt) ? TenantStatus::Trial : TenantStatus::TrialEnded;
    }

    public function trialEndsAt(): ?CarbonImmutable
    {
        return $this->trial_started_at?->addDays(self::TRIAL_DAYS);
    }

    /**
     * @return HasOne<TenantMembership, $this>
     */
    public function ownerMembership(): HasOne
    {
        return $this->hasOne(TenantMembership::class)->where('role', TenantRole::Owner);
    }

    /**
     * @return HasOne<TenantInvitation, $this>
     */
    public function openInvitation(): HasOne
    {
        return $this->hasOne(TenantInvitation::class)->open();
    }

    /**
     * @return HasMany<TenantInvitation, $this>
     */
    public function invitations(): HasMany
    {
        return $this->hasMany(TenantInvitation::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'trial_started_at' => 'immutable_datetime',
            'active_until' => 'immutable_datetime',
            'deactivated_at' => 'immutable_datetime',
        ];
    }
}
