<?php

declare(strict_types=1);

namespace App\Modules\Leave\Support;

use App\Modules\Leave\Contracts\VacationTerms;
use App\Modules\Leave\Data\CompanyVacationTerms;
use App\Modules\Leave\Enums\PolicyScope;
use App\Modules\Leave\Models\LeavePolicyAssignment;
use App\Modules\Leave\Models\LeaveSetting;
use App\Modules\Tenancy\Contracts\Companies;

/** The terms of the policy that applies to everybody by default: the company's own, or the law when it set none. */
final readonly class PolicyVacationTerms implements VacationTerms
{
    public function __construct(private Companies $companies) {}

    public function company(): ?CompanyVacationTerms
    {
        $setting = LeaveSetting::query()->first();

        if ($setting === null || $this->companies->current()->stopped('vacations')) {
            return null;
        }

        $assignment = LeavePolicyAssignment::query()->with('policy.rows')->where('scope', PolicyScope::Default)->first();
        $terms = $assignment?->policy === null ? PolicyTerms::statutory($setting->statutory_work_week) : PolicyResolver::termsOf($assignment->policy);
        $days = $terms->daysFor(1, PeriodWindow::today());

        return new CompanyVacationTerms($days['statutory'] + $days['extra'], $terms->premiumPercent);
    }
}
