<?php

declare(strict_types=1);

namespace App\Modules\Seating\Support;

use App\Modules\Seating\Enums\RowState;
use App\Modules\Tenancy\Data\RouteProgress;

/**
 * The six stages of the Ley Silla route (board V2-Ruta-Silla) from the thirteen requirements of a center: a stage is done
 * when each of its requirements is met, a warning included (a reassessment due, a regulation not yet handed out). The route
 * does not apply where the center declared that nobody works standing and has no position registered.
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
     * @param  list<ComplianceRow>  $rows
     * @param  string|null  $declaredOn  the day of the declaration, as the design system writes it
     */
    public static function from(array $rows, bool $nobodyStanding, bool $hasPositions, ?string $declaredOn): RouteProgress
    {
        if ($nobodyStanding && ! $hasPositions) {
            return RouteProgress::notApplicable("Nadie trabaja de pie · declarado el {$declaredOn}");
        }

        $met = [];

        foreach ($rows as $row) {
            $met[$row->key] = $row;
        }

        $stages = array_map(fn (array $keys): bool => array_all($keys, fn (string $key): bool => ($met[$key]->state ?? RowState::Missing) === RowState::Met), self::STAGES);
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
