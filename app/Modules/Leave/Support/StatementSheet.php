<?php

declare(strict_types=1);

namespace App\Modules\Leave\Support;

use App\Modules\Leave\Models\LeaveEntitlement;
use App\Modules\Leave\Models\LeaveStatement;
use App\Modules\Organization\Contracts\Roster;
use App\Modules\Organization\Contracts\Seniority;
use App\Modules\Tenancy\Contracts\Companies;
use Carbon\CarbonImmutable;

/**
 * What the annual statement of LFT art. 81 says (criterio C-10), read from the period it belongs to: seniority, days, when
 * to enjoy them and the premium as a percent. The same sheet is read by the person in their portal and printed by RH. Days
 * and dates only, never an amount.
 */
final readonly class StatementSheet
{
    public function __construct(private Companies $companies, private Roster $roster, private Seniority $seniority) {}

    /**
     * @return array{company: string, name: string, startedOn: string, years: int, days: int, enjoyFrom: string, enjoyBy: string, premiumPercent: int, issuedOn: string, acknowledgedOn: string|null}
     */
    public function for(LeaveStatement $statement): array
    {
        $period = LeaveEntitlement::query()->findOrFail($statement->leave_entitlement_id);
        $started = $this->seniority->startDatesFor([$statement->employee_id])[$statement->employee_id] ?? $period->anniversary_on;

        return [
            'company' => $this->companies->current()->tradeName,
            'name' => $this->roster->find($statement->employee_id)->name ?? '',
            'startedOn' => self::day($started),
            'years' => $period->years_of_service,
            'days' => $period->statutory_days + $period->extra_days,
            'enjoyFrom' => self::day($period->anniversary_on),
            'enjoyBy' => self::day($period->enjoy_by),
            'premiumPercent' => $period->premium_percent,
            'issuedOn' => self::localDay($statement->issued_at),
            'acknowledgedOn' => $statement->acknowledged_at === null ? null : self::localDay($statement->acknowledged_at),
        ];
    }

    /** "15 de marzo de 2026". */
    private static function day(CarbonImmutable $date): string
    {
        return $date->isoFormat('D [de] MMMM [de] YYYY');
    }

    /** An instant, at the day the company lives it. */
    private static function localDay(CarbonImmutable $moment): string
    {
        return self::day($moment->setTimezone(PeriodWindow::today()->getTimezone()));
    }
}
