<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Actions;

use App\Modules\Campaigns\Enums\CampaignKind;
use App\Modules\Campaigns\Enums\CampaignStatus;
use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Campaigns\Notifications\NextEvaluationDue;
use App\Modules\Campaigns\Support\CampaignClock;
use App\Modules\Campaigns\Support\CompanyCampaignRecords;
use App\Modules\Tenancy\Contracts\Companies;
use App\Modules\Tenancy\Contracts\WorkCenters;
use App\Modules\Tenancy\Data\CompanySummary;
use App\Modules\Tenancy\Data\WorkCenterSummary;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * «Se acerca tu próxima evaluación» (rediseño v2, docs/02 «Ciclo de vida»): each day, tells each company's owner of the
 * centers whose latest NOM-035 evaluation turns 2 years old (num. 7.9) exactly 60 days from today, once, in one email. A
 * center whose next evaluation is already drafted or running is left out, as is an archived one.
 */
final readonly class RemindNextEvaluations
{
    public const int DAYS_BEFORE = 60;

    public function __construct(private Companies $companies, private WorkCenters $workCenters) {}

    /**
     * @return int how many emails were sent.
     */
    public function handle(): int
    {
        $sent = 0;

        foreach (Campaign::query()->withoutGlobalScopes()->where('kind', CampaignKind::Nom035Evaluation)->where('status', CampaignStatus::Closed)->distinct()->orderBy('tenant_id')->get(['tenant_id'])->map(fn (Campaign $campaign): string => $campaign->tenant_id) as $tenantId) {
            try {
                // Each in its own company, as the system.
                Context::scope(function () use ($tenantId, &$sent): void {
                    $company = $this->companies->enter($tenantId);

                    if (! $company->blocked) {
                        $sent += $this->remind($company);
                    }
                }, ['user_id' => null]);
            } catch (Throwable $failure) {
                // One company's failure never keeps the others' emails from going out.
                report($failure);
            }
        }

        Log::info('campaign.next_evaluations_reminded', ['sent' => $sent]);

        return $sent;
    }

    private function remind(CompanySummary $company): int
    {
        $owner = $this->companies->contact();
        $due = CampaignClock::today()->addDays(self::DAYS_BEFORE);
        $centers = array_values(array_filter($this->workCenters->all(), fn (WorkCenterSummary $center): bool => ! $center->archived));
        $evaluations = Campaign::query()->where('kind', CampaignKind::Nom035Evaluation)
            ->whereIn('work_center_id', array_map(fn (WorkCenterSummary $center): string => $center->id, $centers))
            ->get(['work_center_id', 'status', 'closed_at']);
        $turning = array_values(array_filter($centers, function (WorkCenterSummary $center) use ($evaluations, $due): bool {
            $own = $evaluations->where('work_center_id', $center->id);
            $latest = $own->where('status', CampaignStatus::Closed)->max('closed_at');

            return $own->every(fn (Campaign $evaluation): bool => $evaluation->status === CampaignStatus::Closed)
                && $latest instanceof CarbonImmutable
                && $latest->addYears(CompanyCampaignRecords::CYCLE_YEARS)->setTimezone(config()->string('app.display_timezone'))->isSameDay($due);
        }));

        if ($turning === [] || $owner === null) {
            return 0;
        }

        $url = count($turning) === 1 ? route('tenant.nom035.route', [$company->slug, $turning[0]->id]) : route('tenant.campaigns.index', $company->slug);
        Notification::route('mail', $owner)->notify(new NextEvaluationDue($company, array_map(fn (WorkCenterSummary $center): string => $center->name, $turning), CampaignClock::day($due), $url));

        return 1;
    }
}
