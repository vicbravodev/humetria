<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Actions;

use App\Actions\RecordAudit;
use App\Modules\Campaigns\Enums\CampaignKind;
use App\Modules\Campaigns\Enums\CampaignStatus;
use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Campaigns\Notifications\SurveyInvitation;
use App\Modules\Campaigns\Support\CampaignInstruments;
use App\Modules\Organization\Contracts\Roster;
use App\Modules\Organization\Data\RosterEntry;
use App\Modules\Tenancy\Contracts\CurrentTenant;
use App\Modules\Tenancy\Contracts\WorkCenters;
use App\Modules\Tenancy\Data\WorkCenterSummary;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Applies the confidential survey of a work center's seats and rests to everyone active in it, with no sample. It is
 * launched at once, like a Guía I by event; a center has one open at a time. Those with an email are invited, the rest wait.
 */
final readonly class LaunchSeatingSurvey
{
    public function __construct(
        private CurrentTenant $currentTenant,
        private WorkCenters $workCenters,
        private Roster $roster,
        private QueueSurveyInvitations $queueInvitations,
        private RecordAudit $recordAudit,
    ) {}

    /**
     * @throws ValidationException when the work center is not active, has nobody, or already has a survey open.
     */
    public function handle(string $workCenterId, CarbonImmutable $closesOn, int $createdBy): Campaign
    {
        $workCenter = collect($this->workCenters->all())->first(fn (WorkCenterSummary $summary): bool => $summary->id === $workCenterId);
        $people = $workCenter === null || $workCenter->archived ? [] : $this->roster->activeIn($workCenterId);

        if ($people === []) {
            throw ValidationException::withMessages(['work_center_id' => 'Elige un centro de trabajo activo con personas.']);
        }

        try {
            return DB::transaction(fn (): Campaign => $this->launch($workCenterId, $closesOn, $createdBy, $people));
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['work_center_id' => 'Este centro ya tiene una encuesta abierta.']);
        }
    }

    /**
     * @param  list<RosterEntry>  $people
     */
    private function launch(string $workCenterId, CarbonImmutable $closesOn, int $createdBy, array $people): Campaign
    {
        $campaign = Campaign::query()->create([
            'tenant_id' => $this->currentTenant->id(),
            'work_center_id' => $workCenterId,
            'kind' => CampaignKind::SeatingSurvey,
            'status' => CampaignStatus::Active,
            'instruments' => ['seating.survey'],
            'closes_on' => $closesOn,
            'launched_at' => now(),
            'created_by' => $createdBy,
        ]);
        $participants = CampaignInstruments::enroll($campaign, array_fill_keys(array_map(fn (RosterEntry $person): string => $person->id, $people), false));
        $queue = [];

        foreach ($people as $person) {
            $queue[$participants[$person->id]] = SurveyInvitation::routes($person) !== [];
        }

        $invited = $this->queueInvitations->handle($campaign, $queue);

        $counts = ['participants' => count($people), 'invited' => $invited];
        Log::info('campaign.launched', ['campaign_id' => $campaign->id, ...$counts]);
        $this->recordAudit->handle('campaign.launched', $campaign, [...$counts, 'instruments' => $campaign->instruments]);

        return $campaign;
    }
}
