<?php

declare(strict_types=1);

namespace App\Modules\Results\Models;

use App\Concerns\BelongsToCurrentTenant;
use App\Modules\Results\Enums\Destination;
use App\Modules\Results\Enums\HowTold;
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
 * That a person whose Guía I requires a clinical assessment was channeled (num. 5.5, 5.8 c): where to (or that they did not
 * want care), how they were told, when, a note without clinical detail and the day to ask how they are (board
 * V4-N035-Canalizacion). Never an answer or a diagnosis. Health data about a named person: only owner and RH reach it, and
 * none of it goes to a log, the audit trail or an email.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $campaign_id
 * @property string $employee_id
 * @property ReferralStatus $status
 * @property string $referred_on Y-m-d, the day as written in Mexico, not a moment.
 * @property Destination $destination
 * @property HowTold $told
 * @property string|null $note
 * @property string|null $remind_on Y-m-d, the day to ask how the person is; null without a reminder.
 * @property CarbonImmutable|null $reminded_at
 * @property string|null $institution the free text of the records written before the redesign
 * @property int $updated_by
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
#[Table('screening_referrals')]
#[Fillable(['tenant_id', 'campaign_id', 'employee_id', 'status', 'destination', 'told', 'referred_on', 'note', 'remind_on', 'reminded_at', 'institution', 'updated_by'])] // @pest-mutate-ignore: RemoveArrayItem
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
        return ['status' => ReferralStatus::class, 'destination' => Destination::class, 'told' => HowTold::class, 'reminded_at' => 'immutable_datetime'];
    }
}
