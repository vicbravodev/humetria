<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Actions;

use App\Actions\RecordAudit;
use App\Modules\Campaigns\Enums\CampaignKind;
use App\Modules\Campaigns\Enums\CampaignStatus;
use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Campaigns\Support\CampaignInstruments;
use App\Modules\Organization\Contracts\Roster;
use App\Modules\Organization\Data\RosterEntry;
use App\Modules\Tenancy\Contracts\CurrentTenant;
use App\Modules\Tenancy\Contracts\WorkCenters;
use App\Modules\Tenancy\Data\WorkCenterSummary;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Applies Guía I to the people who suffered or witnessed a severe traumatic event at work, including on the direct
 * commute, so whoever needs it is channeled to care (num. 5.5, criterio C-05). Launched at once; it may run while an
 * evaluation of the same work center is open.
 */
final readonly class LaunchTraumaCampaign
{
    public function __construct(
        private CurrentTenant $currentTenant,
        private WorkCenters $workCenters,
        private Roster $roster,
        private QueueSurveyInvitations $queueInvitations,
        private RecordAudit $recordAudit,
    ) {}

    /**
     * @param  list<string>  $employeeIds
     *
     * @throws ValidationException when the work center is not active or someone chosen does not work there.
     */
    public function handle(string $workCenterId, array $employeeIds, CarbonImmutable $closesOn, int $createdBy): Campaign
    {
        $workCenter = collect($this->workCenters->all())->first(fn (WorkCenterSummary $summary): bool => $summary->id === $workCenterId);

        if ($workCenter === null || $workCenter->archived) {
            throw ValidationException::withMessages(['work_center_id' => 'Elige un centro de trabajo activo.']);
        }

        // Anything that is not an active person of this work center (another one, another company, not an id) is refused.
        $active = collect($this->roster->activeIn($workCenterId))->keyBy(fn (RosterEntry $person): string => $person->id);
        $chosen = array_values(array_unique($employeeIds));

        if ($chosen === [] || array_diff($chosen, $active->keys()->all()) !== []) {
            throw ValidationException::withMessages(['employee_ids' => 'Elige solo personas activas de este centro de trabajo.']);
        }

        return DB::transaction(function () use ($workCenterId, $chosen, $active, $closesOn, $createdBy): Campaign {
            $campaign = Campaign::query()->create([
                'tenant_id' => $this->currentTenant->id(),
                'work_center_id' => $workCenterId,
                'kind' => CampaignKind::Nom035Trauma,
                'status' => CampaignStatus::Active,
                'instruments' => ['nom035.guide-i'],
                'closes_on' => $closesOn,
                'launched_at' => now(),
                'created_by' => $createdBy,
            ]);
            $queue = [];

            foreach ($chosen as $employeeId) {
                $participant = $campaign->participants()->create(['tenant_id' => $campaign->tenant_id, 'employee_id' => $employeeId]);
                CampaignInstruments::give($participant, $campaign->instruments);
                $queue[$participant->id] = $active->get($employeeId)?->email !== null;
            }

            $invited = $this->queueInvitations->handle($campaign, $queue);

            $counts = ['participants' => count($chosen), 'invited' => $invited];
            Log::info('campaign.launched', ['campaign_id' => $campaign->id, ...$counts]);
            $this->recordAudit->handle('campaign.launched', $campaign, [...$counts, 'instruments' => $campaign->instruments]);

            return $campaign;
        });
    }
}
