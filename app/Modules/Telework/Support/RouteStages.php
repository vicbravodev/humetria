<?php

declare(strict_types=1);

namespace App\Modules\Telework\Support;

use App\Modules\Telework\Enums\RowState;
use App\Modules\Tenancy\Data\RouteProgress;

/**
 * The six stages of the NOM-037 route (board V2-Ruta-N037) from the rows of the inspection table 10.3 of a center. A stage
 * is done when each of its rows is done or does not apply; the sixth, the dossier, when the five before it are, since
 * Humetría puts it together from them. Where nobody teleworks nor is about to (5.1), the center's answer to «¿Te aplica?»
 * decides (decisión A2): without one it waits for it; with a no it does not apply; with a yes it starts, and registering
 * the people, their places and their equipment stay ahead, since nobody is there yet.
 */
final class RouteStages
{
    /** The numerals of each of the first five stages. */
    private const array STAGES = [
        ['5.2', '5.3', '5.6', '5.8', '5.10', '5.13', '5.14'],
        ['5.1', '5.1.2'],
        ['5.1.1', '5.4 y 7', '5.5', '5.5.1', '5.12'],
        ['5.7', '5.9 y 8'],
        ['5.11'],
    ];

    private const array STATUS = [
        'prepara los documentos de apoyo',
        'registra a quién teletrabaja y dónde',
        'revisa que cada lugar sea seguro',
        'entrega equipo y capacita',
        'atiende lo que avisan las personas',
    ];

    /**
     * @param  list<ComplianceRow>  $rows
     * @param  bool|null  $hasTeleworkers  what the center declared, null when it has not
     * @param  string|null  $declaredOn  the day of the declaration, as the design system writes it
     */
    public static function from(array $rows, ?bool $hasTeleworkers = null, ?string $declaredOn = null): RouteProgress
    {
        $done = [];

        foreach ($rows as $row) {
            $done[$row->numeral] = $row->state === RowState::Done || $row->state === RowState::NotApplicable;
        }

        $nobody = array_any($rows, fn (ComplianceRow $row): bool => $row->numeral === '5.1' && $row->state === RowState::NotApplicable);

        if ($nobody && $hasTeleworkers !== true) {
            return $hasTeleworkers === null ? RouteProgress::unanswered() : RouteProgress::notApplicable("Nadie teletrabaja más del 40 % · constancia del {$declaredOn}");
        }

        $stages = array_map(fn (array $numerals): bool => array_all($numerals, fn (string $numeral): bool => $done[$numeral] ?? false), self::STAGES);

        // Declared, but nobody registered yet: the people, their places and their equipment are all ahead.
        foreach ([1, 2, 3] as $stage) {
            $stages[$stage] = $stages[$stage] && ! $nobody;
        }
        $left = array_search(false, $stages, true);
        $status = match (true) {
            $left === false => 'todo al día',
            $left === 0 && ! ($done['5.2'] ?? false) => 'define las reglas',
            default => self::STATUS[$left],
        };

        return new RouteProgress(true, [...$stages, $left === false], $status);
    }
}
