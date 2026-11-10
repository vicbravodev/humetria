<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Actions;

use App\Modules\Engagement\Models\PulseSchedule;
use App\Modules\Engagement\Notifications\PulseUpcoming;
use App\Modules\Engagement\Support\Pulse\OpenCommitments;
use App\Modules\Engagement\Support\Pulse\UpcomingPulse;
use App\Modules\Engagement\Support\SurveyClock;
use App\Modules\Tenancy\Contracts\Companies;
use App\Modules\Tenancy\Contracts\Members;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * Three days before a scheduled pulse goes out (by the display time zone's calendar), tells owner and hr what it will be
 * and how many commitments of the last survey are still open: a pulse nobody launches by hand is still reviewed before it
 * is sent. Once per date: the schedule remembers the date it told about.
 */
final readonly class AnnounceUpcomingPulses
{
    /** How many days before the run the heads-up goes out. */
    public const int DAYS_BEFORE = 3;

    public function __construct(private Companies $companies, private Members $members) {}

    /**
     * @return int how many emails were sent.
     */
    public function handle(): int
    {
        $runsOn = SurveyClock::day()->addDays(self::DAYS_BEFORE)->toDateString();
        $due = PulseSchedule::query()->withoutGlobalScopes()->where('active', true)->where('next_run_on', $runsOn)
            ->where(fn ($query) => $query->whereNull('announced_for')->orWhere('announced_for', '!=', $runsOn))->get();
        $sent = 0;

        foreach ($due as $schedule) {
            try {
                // Each in its own company, as the system.
                Context::scope(function () use ($schedule, &$sent): void {
                    $sent += DB::transaction(fn (): int => $this->announce($schedule));
                }, ['user_id' => null]);
            } catch (Throwable $failure) {
                // One company's failure never keeps the others from being told.
                report($failure);
            }
        }

        return $sent;
    }

    private function announce(PulseSchedule $schedule): int
    {
        $company = $this->companies->enter($schedule->tenant_id);

        if ($company->blocked) {
            return 0;
        }

        $upcoming = UpcomingPulse::resolve($schedule->frequency, $schedule->next_mode);
        $managers = $this->members->complaintManagers();

        foreach ($managers as $member) {
            Notification::route('mail', $member->email)->notify(new PulseUpcoming(
                $company,
                $schedule->next_run_on,
                $upcoming->mode->label(),
                count($upcoming->refs),
                OpenCommitments::latest()['count'] ?? 0,
                route('tenant.pulse.index', $company->slug),
            ));
        }

        $schedule->update(['announced_for' => $schedule->next_run_on->toDateString()]);
        Log::info('engagement.pulse_announced', ['mode' => $upcoming->mode->value, 'recipients' => count($managers)]);

        return count($managers);
    }
}
