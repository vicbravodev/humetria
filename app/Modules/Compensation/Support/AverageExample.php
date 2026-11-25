<?php

declare(strict_types=1);

namespace App\Modules\Compensation\Support;

use App\Modules\Compensation\Data\CostSubject;
use App\Modules\Compensation\Data\ScenarioInput;
use App\Modules\Compensation\Enums\ScenarioSource;
use App\Modules\Compensation\Models\Scenario;
use App\Modules\Compensation\Support\Calculation\Rate;
use App\Modules\Compensation\Support\Calculation\ScenarioCost;
use App\Modules\Compensation\Support\Calculation\TenureYears;
use Brick\Math\RoundingMode;

/**
 * One worker of an average payroll walked through the cost, step by step, with the same calculation the totals use: the raise, the
 * aguinaldo and the vacation premium that follow it, the integration factor and the SBC, and the employer lines they move. Every
 * amount is for the worker; only a payroll of one kind of worker has one, so a scenario of the roster has none.
 */
final class AverageExample
{
    /**
     * @return ?array<string, int|string>
     */
    public static function of(Scenario $scenario, ScenarioInput $input): ?array
    {
        if ($scenario->source !== ScenarioSource::Average) {
            return null;
        }

        $unit = ScenarioCost::units($input)[0] ?? null;
        $salary = $unit?->dailySalaryCents;

        if ($unit === null || $salary === null) {
            return null;
        }

        $on = $input->effectiveOn;
        $state = $unit->state ?? $input->defaultState;
        $subject = fn (int $daily): CostSubject => new CostSubject($daily, $unit->group, $unit->jobGradeId, TenureYears::at($unit->hiredOn, $on), TenureYears::monthsAt($unit->hiredOn, $on));
        $raised = ScenarioCost::raised($salary, $unit->group, $input);
        $before = ScenarioCost::person($subject($salary), $input->benefits, $input, $state);
        $after = ScenarioCost::person($subject($raised), ScenarioCost::benefitsAfter($input), $input, $state);
        $salaryYear = self::extra($before['lines'], $after['lines'], 'salary:salary');
        $bonusYear = self::extra($before['lines'], $after['lines'], 'benefit:christmas_bonus');
        $premiumYear = self::extra($before['lines'], $after['lines'], 'benefit:vacation_premium');
        $contributionsYear = self::contributions($after['lines']) - self::contributions($before['lines']);
        $totalYear = $salaryYear + $bonusYear + $premiumYear + $contributionsYear;

        return [
            'workers' => $unit->count,
            'monthly_payroll_cents' => intdiv($before['lines']['salary:salary'] ?? 0, 12) * $unit->count,
            'monthly_salary_cents' => intdiv($before['lines']['salary:salary'] ?? 0, 12),
            'monthly_raise_cents' => intdiv($salaryYear, 12),
            'aguinaldo_days' => self::plain($after['terms']->aguinaldoDays, 2),
            'vacation_days' => self::plain($after['terms']->vacationDays, 2),
            'vacation_premium_percent' => self::plain($after['terms']->vacationPremium->times(Rate::scaled(100, 0)), 2),
            'salary_extra_year_cents' => $salaryYear,
            'bonus_extra_year_cents' => $bonusYear,
            'premium_extra_year_cents' => $premiumYear,
            'factor' => (string) $after['terms']->factor()->value()->toScale(4, RoundingMode::HalfUp),
            'sbc_before_cents' => $before['sbc'],
            'sbc_after_cents' => $after['sbc'],
            'contributions_extra_year_cents' => $contributionsYear,
            'total_extra_year_cents' => $totalYear,
            'total_extra_month_cents' => intdiv($totalYear, 12),
        ];
    }

    /**
     * @param  array<string, int>  $before
     * @param  array<string, int>  $after
     */
    private static function extra(array $before, array $after, string $id): int
    {
        return ($after[$id] ?? 0) - ($before[$id] ?? 0);
    }

    /** @param  array<string, int>  $lines */
    private static function contributions(array $lines): int
    {
        $sum = 0;

        foreach ($lines as $id => $cents) {
            $sum += str_starts_with($id, 'contribution:') ? $cents : 0;
        }

        return $sum;
    }

    /**
     * "15" for 15 days, "22.5" for a contract of half days.
     *
     * @param  int<0, max>  $scale
     */
    private static function plain(Rate $days, int $scale): string
    {
        $text = (string) $days->value()->toScale($scale, RoundingMode::HalfUp);

        return str_contains($text, '.') ? rtrim(rtrim($text, '0'), '.') : $text;
    }
}
