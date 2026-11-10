<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Actions;

use App\Modules\Engagement\Enums\SurveyKind;
use App\Modules\Engagement\Enums\SurveyStatus;
use App\Modules\Engagement\Exceptions\SurveyCannotLaunch;
use App\Modules\Engagement\Models\PulseSchedule;
use App\Modules\Engagement\Models\Survey;
use App\Modules\Engagement\Support\Pulse\PulsePlanner;
use App\Modules\Engagement\Support\Pulse\UpcomingPulse;
use App\Modules\Engagement\Support\SurveyClock;
use App\Modules\Tenancy\Contracts\Companies;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Launches, in every company whose schedule is due today (by the display time zone's calendar), its pulse. A cycle the
 * company cannot take (a climate survey in course, a pulse still open, a suspended company, too few people) is skipped
 * with its reason, never left as an orphan draft. Whatever happened, the next date moves into the future, so a command
 * that runs twice, or did not run for days, never piles up pulses. Run every morning.
 */
final readonly class LaunchScheduledPulses
{
    public function __construct(
        private Companies $companies,
        private CreatePulseDraft $createDraft,
        private LaunchSurvey $launch,
        private DeleteSurveyDraft $deleteDraft,
    ) {}

    /**
     * @return int how many pulses were launched.
     */
    public function handle(): int
    {
        $today = SurveyClock::day();
        $due = PulseSchedule::query()->withoutGlobalScopes()->where('active', true)->where('next_run_on', '<=', $today->toDateString())->get();
        $launched = 0;

        foreach ($due as $schedule) {
            try {
                // Each in its own company and transaction, as the system: no person launched it.
                Context::scope(function () use ($schedule, &$launched): void {
                    $launched += DB::transaction(fn (): int => $this->launchFor($schedule));
                }, ['user_id' => null]);
            } catch (Throwable $failure) {
                // One company's failure never keeps the others from launching; tomorrow tries it again.
                Log::error('engagement.pulse_launch_failed', ['tenant_id' => $schedule->tenant_id, 'exception' => $failure::class]);
                report($failure);
            }
        }

        return $launched;
    }

    /**
     * @return int 1 when the pulse was launched, 0 when the cycle was skipped
     */
    private function launchFor(PulseSchedule $schedule): int
    {
        $company = $this->companies->enter($schedule->tenant_id);
        $reason = $company->blocked ? 'company_blocked' : $this->reasonToWait();
        $survey = $reason === null ? $this->launchOrDiscard($schedule) : null;

        if ($survey === null) {
            Log::info('engagement.pulse_skipped', ['reason' => $reason ?? 'too_few_people']);
        } else {
            Log::info('engagement.pulse_launched', ['survey_id' => $survey->id, 'mode' => $survey->pulse_mode?->value, 'invited' => $survey->invited]);
        }

        // Always, launched or not: the next date is in the future and the choice for this one is spent.
        $schedule->update([
            'next_run_on' => PulsePlanner::nextRun($schedule->frequency, $schedule->day_of_month, SurveyClock::day())->toDateString(),
            'next_mode' => null,
            'announced_for' => null,
        ]);

        return $survey === null ? 0 : 1;
    }

    private function reasonToWait(): ?string
    {
        if (Survey::query()->where('kind', SurveyKind::Pulse)->whereIn('status', [SurveyStatus::Draft, SurveyStatus::Active])->exists()) {
            return 'pulse_open';
        }

        return Survey::query()->where('kind', SurveyKind::Climate)->where('status', SurveyStatus::Active)->exists() ? 'climate_active' : null;
    }

    /**
     * Drafts the pulse and launches it; with too few people the draft is thrown away and nothing is left.
     */
    private function launchOrDiscard(PulseSchedule $schedule): ?Survey
    {
        $mode = UpcomingPulse::resolve($schedule->frequency, $schedule->next_mode)->mode;
        $draft = $this->createDraft->handle($mode, $schedule->created_by);

        try {
            return $this->launch->handle($draft->id, SurveyClock::day()->addDays($schedule->open_days), $schedule->created_by);
        } catch (SurveyCannotLaunch) {
            $this->deleteDraft->handle($draft->id);

            return null;
        }
    }
}
