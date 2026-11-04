<?php

declare(strict_types=1);

namespace App\Modules\Compensation\Enums;

/**
 * The assumptions of the long-term projection, each with the value the platform proposes, where it comes from and when it was
 * set. They are all yearly rates in basis points (100 is 1 %); a scenario keeps its own copy and may change any of them.
 */
enum AssumptionKey: string
{
    case Inflation = 'inflation';
    case Turnover = 'turnover';
    case HeadcountGrowth = 'headcount_growth';
    case FutureRevisions = 'future_revisions';
    case FollowingYearsRate = 'following_years_rate';

    public function label(): string
    {
        return match ($this) {
            self::Inflation => 'Inflación',
            self::Turnover => 'Rotación',
            self::HeadcountGrowth => 'Crecimiento de la plantilla',
            self::FutureRevisions => 'Aumento de revisiones futuras',
            self::FollowingYearsRate => 'Aumento adicional de la propuesta en los años siguientes',
        };
    }

    public function defaultBasisPoints(): int
    {
        return match ($this) {
            self::Inflation, self::FutureRevisions => 300,
            self::Turnover, self::HeadcountGrowth, self::FollowingYearsRate => 0,
        };
    }

    public function defaultSource(): string
    {
        return match ($this) {
            self::Inflation => 'Meta de inflación del Banco de México: 3 % con un intervalo de ±1 punto. Hace crecer la UMA y el salario mínimo de los años que la plataforma aún no conoce.',
            self::Turnover => 'Supuesto de la plataforma, sin dato general: captura el de tu empresa. Quien se va se reemplaza con una persona de nuevo ingreso.',
            self::HeadcountGrowth => 'Supuesto de la plataforma, sin dato general: captura el de tu empresa.',
            self::FutureRevisions => 'Supuesto de la plataforma, igual a la meta de inflación: el aumento que darán las revisiones de los años siguientes, con o sin esta propuesta.',
            self::FollowingYearsRate => 'Supuesto de la plataforma: la propuesta no se repite. Si lo capturas, es un aumento más cada año solo con la propuesta.',
        };
    }

    /** The day the platform set its proposal. */
    public function defaultSetOn(): string
    {
        return '2026-11-04';
    }
}
