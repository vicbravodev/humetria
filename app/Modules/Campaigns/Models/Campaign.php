<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Models;

use App\Concerns\BelongsToCurrentTenant;
use App\Modules\Campaigns\Enums\CampaignKind;
use App\Modules\Campaigns\Enums\CampaignStatus;
use App\Modules\Campaigns\Enums\Population;
use Carbon\CarbonImmutable;
use Database\Factories\Campaigns\CampaignFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The application of one or more instruments to the people of a work center, in a period. What was decided at launch
 * (headcount, instruments, who takes Guía III) stays as it was.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $work_center_id
 * @property CampaignKind $kind
 * @property CampaignStatus $status
 * @property list<string> $instruments
 * @property int|null $workers
 * @property Population|null $population
 * @property int|null $sample_size
 * @property array<string, int>|null $quotas
 * @property CarbonImmutable|null $closes_on
 * @property CarbonImmutable|null $launched_at
 * @property CarbonImmutable|null $closed_at
 * @property int $created_by
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
#[Fillable(['tenant_id', 'work_center_id', 'kind', 'status', 'instruments', 'workers', 'population', 'sample_size', 'quotas', 'closes_on', 'launched_at', 'closed_at', 'created_by'])] // @pest-mutate-ignore: RemoveArrayItem
#[UseFactory(CampaignFactory::class)]
final class Campaign extends Model
{
    /** @use HasFactory<CampaignFactory> */
    use BelongsToCurrentTenant, HasFactory, HasVersion4Uuids;

    /**
     * @return HasMany<Participant, $this>
     */
    public function participants(): HasMany
    {
        return $this->hasMany(Participant::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => CampaignKind::class,
            'status' => CampaignStatus::class,
            'population' => Population::class,
            'instruments' => 'array',
            'quotas' => 'array',
            'closes_on' => 'immutable_date',
            'launched_at' => 'immutable_datetime',
            'closed_at' => 'immutable_datetime',
        ];
    }
}
