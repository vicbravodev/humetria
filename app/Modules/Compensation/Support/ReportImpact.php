<?php

declare(strict_types=1);

namespace App\Modules\Compensation\Support;

use App\Modules\Compensation\Data\CostBreakdown;
use App\Modules\Compensation\Data\ScenarioInput;
use App\Modules\Compensation\Enums\CostConcept;
use App\Modules\Compensation\Enums\PayGroup;
use App\Modules\Compensation\Enums\RaiseUnit;
use App\Modules\Compensation\Models\Scenario;
use App\Modules\Compensation\Support\Calculation\Rate;
use App\Modules\Compensation\Support\Calculation\ScenarioCost;

/**
 * What a company wants to read first about a raise, taken from the engine's totals and never from the page: how much the payroll
 * really grows against the raise agreed, what each peso of salary drags with it, the raise against inflation, and the same raise a
 * few points higher and lower.
 */
final class ReportImpact
{
    /** The rungs of the ladder around the raise, in basis points, and the most it shows. */
    private const array STEPS = [-200, 0, 200, 400, 600];

    private const int RUNGS = 4;

    /**
     * @return array{salary_growth_bp: int, payroll_growth_bp: int, drag_hundredths: int, inflation_bp: int, real_raise_bp: int, per_person_month_cents: int}
     */
    public static function of(CostBreakdown $cost, int $inflationBp): array
    {
        $salary = self::salary($cost);
        $growth = self::ratio($salary['recurring'], $salary['before'], 10_000);

        return [
            'salary_growth_bp' => $growth,
            'payroll_growth_bp' => self::ratio($cost->recurringCents, $cost->beforeCents, 10_000),
            'drag_hundredths' => self::ratio($cost->recurringCents, $salary['recurring'], 100),
            'inflation_bp' => $inflationBp,
            'real_raise_bp' => self::ratio(10_000 + $growth, 10_000 + $inflationBp, 10_000) - 10_000,
            'per_person_month_cents' => $cost->people === 0 ? 0 : intdiv(intdiv($cost->recurringCents, $cost->people), 12),
        ];
    }

    /**
     * The raise at four levels two points apart, the scenario's own among them, as each costs; nothing for a raise of a fixed amount.
     * Every group's raise moves by the same points.
     *
     * @return list<array{raise_text: string, current: bool, first_year_cents: int, recurring_cents: int, salary_growth_bp: int, payroll_growth_bp: int, per_person_month_cents: int}>
     */
    public static function ladder(Scenario $scenario, ScenarioInput $input, CostBreakdown $current): array
    {
        if ($scenario->raise_unit === RaiseUnit::Amount) {
            return [];
        }

        $base = $scenario->group_changes['unionized'] ?? 0;
        $trusted = $scenario->group_changes['trusted'] ?? null;
        $rows = [];

        foreach (array_slice(array_values(array_filter(self::STEPS, fn (int $step): bool => $base + $step > 0)), 0, self::RUNGS) as $step) {
            $cost = $step === 0 ? $current : self::moved($input, $step);
            $impact = self::of($cost, 0);
            $rows[] = [
                'raise_text' => self::raiseText($base + $step, $trusted === null || $trusted === $base ? null : $trusted + $step),
                'current' => $step === 0,
                'first_year_cents' => $cost->firstYearCents(),
                'recurring_cents' => $cost->recurringCents,
                'salary_growth_bp' => $impact['salary_growth_bp'],
                'payroll_growth_bp' => $impact['payroll_growth_bp'],
                'per_person_month_cents' => $impact['per_person_month_cents'],
            ];
        }

        return $rows;
    }

    private static function moved(ScenarioInput $input, int $basisPoints): CostBreakdown
    {
        $raises = [];
        $amounts = [];

        foreach (PayGroup::cases() as $group) {
            $raise = ScenarioCost::raiseFor($group, $input);
            $amounts[$group->value] = ScenarioCost::amountFor($group, $input);
            $raises[$group->value] = $raise === null ? Rate::zero() : $raise->plus(Rate::scaled($basisPoints, 4));
        }

        return ScenarioCost::firstYear($input->withChanges($raises, $input->proposed, $amounts));
    }

    /** "6 %", or "5 % · 3 %" when the trusted have a raise of their own. */
    private static function raiseText(int $unionized, ?int $trusted): string
    {
        return PercentBasisPoints::format($unionized).' %'.($trusted === null ? '' : ' · '.PercentBasisPoints::format($trusted).' %');
    }

    /** @return array{before: int, recurring: int} what the salary concept costs before and what the raise adds to it a year */
    private static function salary(CostBreakdown $cost): array
    {
        $before = 0;
        $recurring = 0;

        foreach ($cost->concepts as $concept) {
            if ($concept->type === CostConcept::Salary) {
                $before += $concept->beforeCents;
                $recurring += $concept->recurringCents;
            }
        }

        return ['before' => $before, 'recurring' => $recurring];
    }

    /** `$part` over `$whole` times `$scale`, to the nearest whole; 0 over nothing. */
    private static function ratio(int $part, int $whole, int $scale): int
    {
        return $whole <= 0 ? 0 : intdiv($part * $scale * 2 + $whole, $whole * 2);
    }
}
