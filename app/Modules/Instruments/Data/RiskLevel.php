<?php

declare(strict_types=1);

namespace App\Modules\Instruments\Data;

/**
 * The five risk levels of NOM-035 Guías II and III (II.3 c, III.3 c), from lowest to highest.
 */
enum RiskLevel: string
{
    case Null = 'null';
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';
    case VeryHigh = 'very_high';

    /**
     * The level of a score given the lower bounds of Bajo, Medio, Alto and Muy alto. Each bound belongs to its level:
     * the DOF underlines the "<" after each bound, its way of writing "≤" (criterio C-13).
     *
     * @param  array{0: int, 1: int, 2: int, 3: int}  $cutoffs
     */
    public static function fromCutoffs(int $score, array $cutoffs): self
    {
        return match (true) {
            $score >= $cutoffs[3] => self::VeryHigh,
            $score >= $cutoffs[2] => self::High,
            $score >= $cutoffs[1] => self::Medium,
            $score >= $cutoffs[0] => self::Low,
            default => self::Null,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Null => 'Nulo o despreciable',
            self::Low => 'Bajo',
            self::Medium => 'Medio',
            self::High => 'Alto',
            self::VeryHigh => 'Muy alto',
        };
    }
}
