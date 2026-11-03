<?php

declare(strict_types=1);

namespace App\Modules\Results\Models;

use App\Concerns\BelongsToCurrentTenant;
use App\Modules\Results\Enums\ReferralStatus;
use Carbon\CarbonImmutable;
use Database\Factories\Results\ScreeningReferralFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * That a person whose Guía I requires a clinical assessment was referred or attended, when and where (num. 5.5, 5.8 c).
 * Health data about a named person: only owner and RH reach it, and none of it goes to a log.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $campaign_id
 * @property string $employee_id
 * @property ReferralStatus $status
 * @property string $referred_on Y-m-d, the day as written in Mexico, not a moment.
 * @property string $institution
 * @property int $updated_by
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
#[Table('screening_referrals')]
#[Fillable(['tenant_id', 'campaign_id', 'employee_id', 'status', 'referred_on', 'institution', 'updated_by'])] // @pest-mutate-ignore: RemoveArrayItem
#[UseFactory(ScreeningReferralFactory::class)]
final class ScreeningReferral extends Model
{
    /** @use HasFactory<ScreeningReferralFactory> */
    use BelongsToCurrentTenant, HasFactory, HasVersion4Uuids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['status' => ReferralStatus::class];
    }
}
