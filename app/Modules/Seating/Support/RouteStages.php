<?php

declare(strict_types=1);

namespace App\Modules\Seating\Support;

use App\Modules\Seating\Enums\RowState;
use App\Modules\Tenancy\Data\RouteProgress;

/**
 * The six stages of the Ley Silla route (board V2-Ruta-Silla) from the thirteen requirements of a center: a stage is done
 * when each of its requirements is met (a warning, like a reassessment due, is not), and seats, assessments and pauses wait for
 * a position to be registered. The route does not apply where the center declared that nobody works standing and has no
 * position registered; a center that never declared and has none still has to answer «¿Te aplica?» (decisión A2).
 */
final class RouteStages
{
    /** The requirements of each stage. */
    private const array STAGES = [
        ['r1'],
        ['r4', 'r5', 'r6'],
        ['r2', 'r3', 'r8', 'r9'],
        ['r7', 'r11'],
        ['r10'],
        ['r12', 'r13'],
    ];

    private const array STATUS = [
        'identifica los puestos de pie',
        'pon asientos donde hacen falta',
        'evalúa a cada persona que trabaja de pie',
        'acuerda pausas y medidas',
        'actualiza tu reglamento interior',
        'documenta para la inspección',
    ];

    /**
     * The route of a center from what it registered: its requirements, its declaration and its positions.
     *
     * @param  list<ComplianceRow>  $rows  the requirements of `$facts`
     */
    public static function ofFacts(ComplianceFacts $facts, array $rows): RouteProgress
    {
        $declaredOn = $facts->profile === null ? null : str_replace('.', '', $facts->profile->declared_on->isoFormat('D MMM YYYY'));

        return self::from($rows, $facts->profile->no_standing_positions ?? false, $facts->positions->isNotEmpty(), $declaredOn);
    }

    /**
     * Where a requirement falls in the route: its stage, then its place in that stage.
     */
    public static function rank(string $key): int
    {
        foreach (self::STAGES as $stage => $keys) {
            $at = array_search($key, $keys, true);

            if ($at !== false) {
                return $stage * 100 + $at;
            }
        }

        return count(self::STAGES) * 100;
    }

    /**
     * @param  list<ComplianceRow>  $rows
     * @param  string|null  $declaredOn  the day of the declaration, as the design system writes it; null without one
     */
    public static function from(array $rows, bool $nobodyStanding, bool $hasPositions, ?string $declaredOn): RouteProgress
    {
        if (! $hasPositions && $declaredOn === null) {
            return RouteProgress::unanswered();
        }

        if ($nobodyStanding && ! $hasPositions) {
            return RouteProgress::notApplicable("Nadie trabaja de pie · constancia del {$declaredOn}");
        }

        $met = [];

        foreach ($rows as $row) {
            $met[$row->key] = $row;
        }

        $stages = array_map(fn (array $keys): bool => array_all($keys, fn (string $key): bool => in_array($met[$key]->state ?? RowState::Missing, [RowState::Met, RowState::NotApplicable], true)), self::STAGES);

        // Seats, assessments and pauses are for the positions: without one, they wait for the first stage.
        foreach ([1, 2, 3] as $stage) {
            $stages[$stage] = $stages[$stage] && $hasPositions;
        }

        $left = array_search(false, $stages, true);
        $unassessed = isset($met['r2']) && $met['r2']->state === RowState::Missing ? $met['r2']->count : 0;
        $status = match (true) {
            $left === false => 'todo al día',
            $left === 2 && $unassessed > 0 => "evalúa a {$unassessed} ".($unassessed === 1 ? 'persona' : 'personas'),
            default => self::STATUS[$left],
        };

        return new RouteProgress(true, $stages, $status);
    }
}
