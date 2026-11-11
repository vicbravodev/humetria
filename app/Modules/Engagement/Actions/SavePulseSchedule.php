<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Actions;

use App\Actions\RecordAudit;
use App\Modules\Engagement\Enums\PulseFrequency;
use App\Modules\Engagement\Enums\PulseMode;
use App\Modules\Engagement\Models\PulseSchedule;
use App\Modules\Engagement\Support\Pulse\PriorityDrivers;
use App\Modules\Engagement\Support\Pulse\PulsePlanner;
use App\Modules\Engagement\Support\SurveyClock;
use App\Modules\Tenancy\Contracts\CurrentTenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Creates or changes the only schedule of the company and works its next run out from today, by the display time
 * zone's calendar. The mode RH picks for the next pulse must be one the company can use.
 */
final readonly class SavePulseSchedule
{
    public function __construct(private CurrentTenant $tenant, private RecordAudit $recordAudit) {}

    /**
     * @throws ValidationException when the mode chosen for the next pulse is a follow-up and there are no priorities
     */
    public function handle(PulseFrequency $frequency, int $dayOfMonth, int $openDays, bool $active, ?PulseMode $nextMode, int $userId): PulseSchedule
    {
        if ($nextMode !== null && ! in_array($nextMode, PulsePlanner::available(PriorityDrivers::ofLatestClimate() !== []), true)) {
            throw ValidationException::withMessages(['next_mode' => 'Aún no hay prioridades de un clima para dar seguimiento.']);
        }

        return DB::transaction(function () use ($frequency, $dayOfMonth, $openDays, $active, $nextMode, $userId): PulseSchedule {
            $schedule = PulseSchedule::query()->updateOrCreate(
                ['tenant_id' => $this->tenant->id()],
                [
                    'frequency' => $frequency,
                    'day_of_month' => $dayOfMonth,
                    'open_days' => $openDays,
                    'active' => $active,
                    'next_run_on' => PulsePlanner::nextRun($frequency, $dayOfMonth, SurveyClock::day())->toDateString(),
                    'next_mode' => $nextMode,
                    'announced_for' => null,
                    'created_by' => $userId,
                ],
            );

            $details = ['frequency' => $frequency->value, 'day_of_month' => $dayOfMonth, 'open_days' => $openDays, 'active' => $active, 'next_mode' => $nextMode?->value];
            Log::info('engagement.pulse_schedule_saved', $details);
            $this->recordAudit->handle('engagement.pulse_schedule_saved', $schedule, $details);

            return $schedule;
        });
    }
}
