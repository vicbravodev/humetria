<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Actions;

use App\Modules\Engagement\Enums\SurveyStatus;
use App\Modules\Engagement\Models\Survey;
use App\Modules\Engagement\Support\SurveyClock;
use App\Modules\Tenancy\Contracts\Companies;
use Illuminate\Support\Facades\Context;
use Throwable;

/**
 * Closes, in every company, the surveys whose last day is over in the display time zone. Run every hour.
 */
final readonly class CloseExpiredSurveys
{
    public function __construct(private Companies $companies, private CloseSurvey $closeSurvey) {}

    /**
     * @return int how many were closed.
     */
    public function handle(): int
    {
        $expired = Survey::query()->withoutGlobalScopes()
            ->where('status', SurveyStatus::Active)
            ->where('closes_on', '<', SurveyClock::day()->toDateString())
            ->get(['id', 'tenant_id']);

        $closed = 0;

        foreach ($expired as $survey) {
            try {
                // Each in its own company, as the system: no person closed it.
                Context::scope(function () use ($survey): void {
                    $this->companies->enter($survey->tenant_id);
                    $this->closeSurvey->handle($survey->id);
                }, ['user_id' => null]);
                $closed++;
            } catch (Throwable $failure) {
                // One company's failure never keeps the others open; the next hour tries it again.
                report($failure);
            }
        }

        return $closed;
    }
}
