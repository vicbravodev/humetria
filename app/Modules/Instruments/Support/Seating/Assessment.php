<?php

declare(strict_types=1);

namespace App\Modules\Instruments\Support\Seating;

use App\Modules\Instruments\Contracts\SeatingAssessment;
use App\Modules\Instruments\Data\AssessmentDefinition;
use App\Modules\Instruments\Data\AssessmentScore;
use App\Modules\Instruments\Data\ConditionDefinition;
use App\Modules\Instruments\Data\RiskLevel;
use App\Modules\Instruments\Data\SeatPlacement;
use InvalidArgumentException;

/**
 * The seven score tables of Quinta D of the Disposiciones of 2025-07-17, as the DOF words them, and Figura 1. Each condition
 * is worth 1 to 3 points; 7 is Bajo, 8 to 14 Medio and 15 to 21 Alto. The levels have no Nulo, so RiskLevel::fromCutoffs
 * does not apply.
 */
final class Assessment implements SeatingAssessment
{
    public const string VERSION = 'dof-2025-07-17';

    private const int LOWEST_TOTAL = 7;

    private const int LAST_MEDIUM_TOTAL = 14;

    public function definition(): AssessmentDefinition
    {
        return new AssessmentDefinition(self::VERSION, [
            new ConditionDefinition('standing_time', 'a', 'Tiempo que la persona trabajadora permanece de pie (duración de la bipedestación diaria sin descanso en posición sedente)', [
                1 => 'menor o igual a 30 minutos',
                2 => 'mayor a 30 minutos y menor o igual a una hora',
                3 => 'mayor a una hora',
            ]),
            new ConditionDefinition('posture_change', 'b', 'Posibilidad de cambiar de postura', [
                1 => 'Sí, con frecuencia',
                2 => 'Algunas veces',
                3 => 'No puede cambiar de postura',
            ]),
            new ConditionDefinition('surface', 'c', 'Tipo de superficie de trabajo', [
                1 => 'Piso con recubrimiento blando que amortigua (tapete, alfombra ó materiales blandos antifatiga)',
                2 => 'Piso firme pero no duro (por ejemplo, madera, duela o hule)',
                3 => 'Piso duro sin recubrimiento como concreto, cerámica o similares',
            ]),
            new ConditionDefinition('footwear', 'd', 'Características del calzado utilizado', [
                1 => 'Ergonómico, con buen soporte y amortiguación',
                2 => 'Aceptable, pero no específico',
                3 => 'Inadecuado (duro, sin soporte, inseguro)',
            ]),
            new ConditionDefinition('movement_space', 'e', 'Espacio disponible para moverse', [
                1 => 'Amplio y permite libre movimiento y/o desplazamientos amplios',
                2 => 'Limitado, pero sin obstáculos, permite movimiento y/o desplazamientos cortos',
                3 => 'Muy reducido con restricción de movimientos y desplazamientos nulos',
            ]),
            new ConditionDefinition('discomfort', 'f', 'Malestares reportados por la persona trabajadora que realiza actividades en bipedestación', [
                1 => 'Ninguno',
                2 => 'Eventual fatiga en extremidades inferiores; dolor y/o pesadez ocasional en piernas y/o espalda',
                3 => 'Dolor lumbar frecuente, hinchazón y/o calambres',
            ]),
            new ConditionDefinition('pauses', 'g', 'Pausas, tareas alternas o complementarias, sin comprometer su actividad', [
                1 => 'Pausas planeadas por tiempos regulares',
                2 => 'Permite algunas pausas no programadas (informales)',
                3 => 'No tiene pausas y/o no puede sentarse en ningún momento',
            ]),
        ]);
    }

    public function score(array $points): AssessmentScore
    {
        $keys = array_map(fn (ConditionDefinition $condition): string => $condition->key, $this->definition()->conditions);

        if (array_diff_key($points, array_flip($keys)) !== []) {
            throw new InvalidArgumentException('La evaluación incluye una condicionante que no existe.');
        }

        foreach ($keys as $key) {
            if (! array_key_exists($key, $points)) {
                throw new InvalidArgumentException('Faltan condicionantes: las siete deben tener su puntaje.');
            }

            if ($points[$key] < 1 || $points[$key] > 3) {
                throw new InvalidArgumentException('Cada condicionante vale de 1 a 3 puntos.');
            }
        }

        $total = array_sum($points);

        return new AssessmentScore($total, match (true) {
            $total === self::LOWEST_TOTAL => RiskLevel::Low,
            $total <= self::LAST_MEDIUM_TOTAL => RiskLevel::Medium,
            default => RiskLevel::High,
        });
    }

    public function placement(bool $canWorkSeated, bool $hasSpaceForSeat, bool $needsToBeNearby): SeatPlacement
    {
        return match (true) {
            $canWorkSeated && $hasSpaceForSeat => SeatPlacement::AtWorkstation,
            $needsToBeNearby => SeatPlacement::Nearby,
            default => SeatPlacement::WithinCenter,
        };
    }
}
