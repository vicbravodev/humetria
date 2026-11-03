<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Support;

use App\Modules\Campaigns\Actions\CloseCampaign;
use App\Modules\Campaigns\Actions\LaunchSeatingSurvey;
use App\Modules\Campaigns\Contracts\CenterSurveys;
use App\Modules\Campaigns\Data\CenterSurvey;
use App\Modules\Campaigns\Enums\CampaignKind;
use App\Modules\Campaigns\Enums\CampaignStatus;
use App\Modules\Campaigns\Enums\InstrumentStatus;
use App\Modules\Campaigns\Exceptions\CampaignStateChanged;
use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Campaigns\Models\Participant;
use App\Modules\Campaigns\Models\ParticipantInstrument;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

final readonly class CompanyCenterSurveys implements CenterSurveys
{
    public function __construct(private LaunchSeatingSurvey $launch, private CloseCampaign $close) {}

    public function launch(string $workCenterId, CarbonImmutable $closesOn, int $createdBy): CenterSurvey
    {
        return $this->summary($this->launch->handle($workCenterId, $closesOn, $createdBy));
    }

    public function close(string $workCenterId): CenterSurvey
    {
        $open = Campaign::query()->where('work_center_id', $workCenterId)->where('kind', CampaignKind::SeatingSurvey)->where('status', CampaignStatus::Active)->first();

        if ($open === null) {
            throw ValidationException::withMessages(['survey' => 'Este centro no tiene una encuesta abierta.']);
        }

        try {
            $this->close->handle($open);
        } catch (CampaignStateChanged) {
            throw ValidationException::withMessages(['survey' => 'La encuesta ya se cerró.']);
        }

        return $this->summary($open->refresh());
    }

    public function forCenter(string $workCenterId): array
    {
        return array_values(Campaign::query()->where('work_center_id', $workCenterId)->where('kind', CampaignKind::SeatingSurvey)->orderByDesc('created_at')->orderByDesc('id')->get()
            ->map(fn (Campaign $campaign): CenterSurvey => $this->summary($campaign))->all());
    }

    private function summary(Campaign $campaign): CenterSurvey
    {
        return new CenterSurvey(
            $campaign->id,
            $campaign->status->value,
            $campaign->closes_on?->toDateString(),
            Participant::query()->where('campaign_id', $campaign->id)->count(),
            ParticipantInstrument::query()->whereIn('participant_id', Participant::query()->where('campaign_id', $campaign->id)->select('id'))->where('status', InstrumentStatus::Completed)->count(),
        );
    }
}
