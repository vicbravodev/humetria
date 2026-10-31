<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Models;

use App\Concerns\BelongsToCurrentTenant;
use App\Modules\Campaigns\Enums\CampaignKind;
use App\Modules\Campaigns\Enums\CampaignStatus;
use App\Modules\Campaigns\Enums\Population;
use App\Modules\Campaigns\Exceptions\CampaignStateChanged;
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
 * @property string|null $invitation_batch
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
#[Fillable(['tenant_id', 'work_center_id', 'kind', 'status', 'instruments', 'workers', 'population', 'sample_size', 'quotas', 'closes_on', 'launched_at', 'closed_at', 'created_by', 'invitation_batch'])] // @pest-mutate-ignore: RemoveArrayItem
#[UseFactory(CampaignFactory::class)]
final class Campaign extends Model
{
    /** @use HasFactory<CampaignFactory> */
    use BelongsToCurrentTenant, HasFactory, HasVersion4Uuids;

    /**
     * How many times a transaction that locks a campaign and then its people runs before giving up on a deadlock.
     * Every such writer locks the campaign first, so a deadlock is rare; the retries absorb the ones left.
     */
    public const int DEADLOCK_ATTEMPTS = 3;

    /**
     * Re-reads the campaign under lock, inside the caller's transaction, so a state checked on a copy read earlier
     * cannot have changed meanwhile: two launches, a redraw racing a launch, or two closes, never both pass.
     *
     * @throws CampaignStateChanged when it is no longer in `$status`.
     */
    public static function lockIn(self $campaign, CampaignStatus $status): self
    {
        $locked = self::query()->lockForUpdate()->findOrFail($campaign->id);

        if ($locked->status !== $status) {
            throw new CampaignStateChanged;
        }

        return $locked;
    }

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
