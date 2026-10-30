<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Actions;

use App\Modules\Campaigns\Enums\CampaignStatus;
use App\Modules\Campaigns\Enums\InstrumentStatus;
use App\Modules\Campaigns\Models\ParticipantInstrument;
use Illuminate\Support\Facades\Log;

/**
 * A person no longer works where an active evaluation is applied: what they had pending is cancelled and their link
 * stops working. What they answered stays (criterio C-24).
 */
final class CancelParticipation
{
    /**
     * @param  string|null  $workCenterId  only the evaluations of this work center; every one when null.
     */
    public function handle(string $employeeId, ?string $workCenterId = null): int
    {
        $cancelled = ParticipantInstrument::query()
            ->where('status', InstrumentStatus::Pending)
            ->whereHas('participant', fn ($participant) => $participant
                ->where('employee_id', $employeeId)
                ->whereHas('campaign', fn ($campaign) => $campaign
                    ->where('status', CampaignStatus::Active)
                    ->when($workCenterId !== null, fn ($query) => $query->where('work_center_id', $workCenterId))))
            ->update(['status' => InstrumentStatus::Cancelled, 'cancelled_at' => now()]);

        if ($cancelled > 0) {
            Log::info('campaign_participant.cancelled', ['employee_id' => $employeeId, 'guides' => $cancelled]);
        }

        return $cancelled;
    }
}
