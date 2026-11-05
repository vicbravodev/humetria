<?php

declare(strict_types=1);

namespace App\Modules\Seating\Actions;

use App\Modules\Seating\Enums\AssessmentStatus;
use App\Modules\Seating\Jobs\SendStandingQuestions;
use App\Modules\Seating\Models\RiskAssessment;
use App\Modules\Tenancy\Contracts\Companies;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The weekly reminder of the 2 questions (board V3-Correos): a week and two weeks after an assessment started, while it
 * waits; the job checks whether the person answered. Answering is voluntary, so it is never asked a third time.
 */
final readonly class RemindStandingQuestions
{
    /** Days after the start of an assessment when the person is reminded. */
    private const array DAYS = [7, 14];

    public function __construct(private Companies $companies) {}

    /**
     * @return int how many reminders were queued.
     */
    public function handle(): int
    {
        $queued = 0;
        $zone = config()->string('app.display_timezone');
        $today = CarbonImmutable::now($zone)->startOfDay();

        foreach (RiskAssessment::query()->withoutGlobalScopes()->where('status', AssessmentStatus::Draft)->distinct()->orderBy('tenant_id')->get(['tenant_id'])->map(fn (RiskAssessment $assessment): string => $assessment->tenant_id) as $tenantId) {
            try {
                // Each in its own company, as the system.
                Context::scope(function () use ($tenantId, $zone, $today, &$queued): void {
                    if ($this->companies->enter($tenantId)->blocked) {
                        return;
                    }

                    // ponytail: drafts are read whole per company; a date range in SQL if a company ever has thousands open.
                    foreach (RiskAssessment::query()->where('status', AssessmentStatus::Draft)->get(['employee_id', 'created_at']) as $draft) {
                        if (in_array((int) $draft->created_at->setTimezone($zone)->startOfDay()->diffInDays($today), self::DAYS, true)) {
                            SendStandingQuestions::dispatch($tenantId, $draft->employee_id);
                            $queued++;
                        }
                    }
                }, ['user_id' => null]);
            } catch (Throwable $failure) {
                // One company's failure never keeps the others' reminders from going out.
                report($failure);
            }
        }

        Log::info('seating.standing_questions_reminded', ['queued' => $queued]);

        return $queued;
    }
}
