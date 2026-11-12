<?php

declare(strict_types=1);

namespace App\Modules\FirstSteps\Actions;

use App\Modules\Assistant\Contracts\AiAccess;
use App\Modules\Assistant\Contracts\HumiActivity;
use App\Modules\Attendance\Contracts\AttendanceUsage;
use App\Modules\Campaigns\Contracts\CampaignRecords;
use App\Modules\FirstSteps\Data\FirstStep;
use App\Modules\FirstSteps\Data\FirstStepsData;
use App\Modules\FirstSteps\Data\OtherTool;
use App\Modules\Organization\Contracts\Headcount;
use App\Modules\Seating\Contracts\SeatingApplicability;
use App\Modules\Telework\Contracts\TeleworkApplicability;
use App\Modules\Tenancy\Contracts\Companies;
use App\Modules\Tenancy\Contracts\CompanyModules;
use App\Modules\Tenancy\Contracts\Members;
use App\Modules\Tenancy\Contracts\WorkCenters;
use App\Modules\Tenancy\Data\WorkCenterSummary;

/**
 * The steps of a company's guided tour, from its plan and its data: nothing is stored, so a step stays done only while
 * its data is there. Reads the current company; never anything individual.
 */
final readonly class BuildFirstSteps
{
    /** The evaluation tools in the order the third step offers them, with where each one lives. */
    private const array TOOLS = [
        'nom035' => 'tenant.campaigns.index',
        'nom037' => 'tenant.telework.index',
        'seating' => 'tenant.seating.index',
        'attendance' => 'tenant.attendance.index',
    ];

    public function __construct(
        private Companies $companies,
        private WorkCenters $workCenters,
        private Headcount $headcount,
        private CompanyModules $modules,
        private CampaignRecords $campaigns,
        private TeleworkApplicability $telework,
        private SeatingApplicability $seating,
        private AttendanceUsage $attendance,
        private Members $members,
        private AiAccess $ai,
        private HumiActivity $humi,
    ) {}

    public function handle(string $tenantId, int $userId): FirstStepsData
    {
        $slug = $this->companies->current()->slug;
        $link = fn (string $route): string => route($route, ['tenant' => $slug], absolute: false);

        $centers = $this->workCenters->all();
        $steps = [
            new FirstStep('work_centers', array_any($centers, fn (WorkCenterSummary $center): bool => ! $center->archived), $link('tenant.work-centers.index')),
            new FirstStep('people', $this->headcount->ofCompany($tenantId) >= 1, $link('tenant.employees.index')),
        ];

        $enabled = $this->modules->enabledKeys($tenantId);
        $have = array_values(array_filter(array_keys(self::TOOLS), fn (string $tool): bool => in_array($tool, $enabled, true)));
        if ($have !== []) {
            $steps[] = $this->evaluation($have[0], $tenantId, $centers, $link);
        }

        $steps[] = new FirstStep('team', count($this->members->all()) > 1 || $this->members->pendingInvitations() > 0, $link('tenant.members.index'));

        if ($this->ai->available()) {
            $steps[] = new FirstStep('humi', $this->humi->hasAsked($userId), null);
        }

        $others = array_map(fn (string $tool): OtherTool => new OtherTool($tool, $link(self::TOOLS[$tool])), array_slice($have, 1));

        return new FirstStepsData($steps, false, false, $others);
    }

    /**
     * @param  list<WorkCenterSummary>  $centers
     * @param  callable(string): string  $link
     */
    private function evaluation(string $tool, string $tenantId, array $centers, callable $link): FirstStep
    {
        $active = array_values(array_map(
            fn (WorkCenterSummary $center): string => $center->id,
            array_filter($centers, fn (WorkCenterSummary $center): bool => ! $center->archived),
        ));
        // The questions of NOM-037 and Ley Silla are asked per work center: with none yet, that is the place to start.
        $questions = $link($active === [] ? 'tenant.work-centers.index' : 'tenant.work-centers.onboarding');

        return match ($tool) {
            'nom035' => new FirstStep('evaluation', $this->campaigns->anyLaunched(), $link('tenant.campaigns.index'), $tool),
            'nom037' => new FirstStep('evaluation', $this->telework->declarations($active) !== [], $questions, $tool),
            'seating' => new FirstStep('evaluation', $this->seating->declarations($active) !== [], $questions, $tool),
            default => new FirstStep('evaluation', $this->attendance->among([$tenantId]) !== [], $link('tenant.attendance.setup'), $tool),
        };
    }
}
