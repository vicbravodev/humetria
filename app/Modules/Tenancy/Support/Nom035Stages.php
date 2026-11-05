<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Support;

use App\Modules\Tenancy\Data\RouteProgress;

/**
 * The six stages of the NOM-035 route of a work center (board V2-Ruta-N035): the policy, the evaluation, channeling who
 * needs it, sharing the results, acting on the risks and the report. What follows the evaluation is read from the latest
 * one closed within the cycle, so it waits while none is; a center of up to 15 people has no chapter 7 to meet (num. 2 a),
 * so sharing, the Programa and the report are done for it. With every stage done the line says until when, the cycle.
 */
final class Nom035Stages
{
    public static function from(Nom035Facts $facts): RouteProgress
    {
        $evaluated = $facts->standing === 'up_to_date';
        $small = $facts->onlyGuideOne;
        $stages = [
            $facts->policy,
            $evaluated,
            $evaluated && $facts->toChannel === 0,
            $evaluated && ($small || $facts->shared),
            $evaluated && ($small || $facts->program === null || $facts->program === 'done'),
            $evaluated && ($small || $facts->reported),
        ];
        $left = array_search(false, $stages, true);

        return new RouteProgress(true, $stages, match ($left) {
            false => "al día hasta el {$facts->until}",
            0 => 'define tu política de prevención',
            1 => self::evaluation($facts),
            2 => "canaliza a {$facts->toChannel} ".($facts->toChannel === 1 ? 'persona' : 'personas'),
            3 => 'comparte los resultados con tu equipo',
            4 => $facts->program === 'none' ? 'arma el programa de intervención' : 'sigue tu programa de intervención',
            default => 'completa el informe 7.7',
        });
    }

    private static function evaluation(Nom035Facts $facts): string
    {
        if ($facts->running !== null) {
            $closes = match ($facts->running['daysLeft']) {
                0 => 'cierra hoy',
                1 => 'cierra mañana',
                default => "cierra en {$facts->running['daysLeft']} días",
            };

            return "{$facts->running['answered']} de {$facts->running['target']} contestaron, {$closes}";
        }

        return match (true) {
            $facts->draft => 'lanza la evaluación',
            $facts->standing === 'overdue' => "toca evaluar de nuevo: venció el {$facts->until}",
            default => 'evalúa a tu personal',
        };
    }
}
