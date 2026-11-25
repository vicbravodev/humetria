<?php

declare(strict_types=1);

namespace App\Modules\Compensation\Support;

use App\Modules\Compensation\Contracts\CompensationStanding;
use App\Modules\Compensation\Data\ContractStanding;
use App\Modules\Compensation\Enums\ScenarioSource;
use App\Modules\Compensation\Models\GradeSalary;
use App\Modules\Compensation\Models\Scenario;
use App\Modules\Organization\Contracts\CompensationRoster;
use App\Modules\Organization\Contracts\JobGrades;
use Carbon\CarbonImmutable;

/** The counts of the start of Compensación (ContractStatus) that say how far the contract can be costed. */
final readonly class CompanyCompensationStanding implements CompensationStanding
{
    public function __construct(private CompensationRoster $roster, private JobGrades $jobGrades) {}

    public function current(): ContractStanding
    {
        $gaps = $this->roster->gaps();
        $grades = $this->jobGrades->page('', 1)->total();
        $paid = GradeSalary::query()->where('effective_from', '<=', CarbonImmutable::now(config()->string('app.display_timezone'))->toDateString())->distinct()->count('job_grade_id');

        $active = fn (ScenarioSource $source): int => Scenario::query()->where('source', $source->value)->whereNull('archived_at')->count();

        return new ContractStanding($gaps->total, $gaps->costed, max(0, $grades - $paid), $active(ScenarioSource::Roster), $active(ScenarioSource::Average));
    }
}
