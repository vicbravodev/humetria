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
            self::Inflation => 300,
            self::FutureRevisions => 730,
            self::HeadcountGrowth => 130,
            self::Turnover, self::FollowingYearsRate => 0,
        };
    }

    public function defaultSource(): string
    {
        return match ($this) {
            self::Inflation => 'Meta de inflación del Banco de México: 3 % con un intervalo de ±1 punto (CEFP, nota 012/2024). Hace crecer la UMA y el salario mínimo de los años que la plataforma aún no conoce.',
            self::Turnover => 'Sin dato oficial para este uso: el IMSS no publica una tasa anual de rotación de plantilla fija y las cifras que hay mezclan empleo eventual. Captura la de tu empresa. Quien se va se reemplaza con una persona de nuevo ingreso.',
            self::HeadcountGrowth => 'Crecimiento del empleo formal registrado en el IMSS en 2025: 1.3 % (informe del IMSS, cierre de diciembre de 2025). Es un dato nacional: captura el de tu empresa.',
            self::FutureRevisions => 'Incremento salarial nominal promedio de las revisiones contractuales de jurisdicción federal de enero a octubre de 2025: 7.3 %, en 2,896 revisiones (STPS; DOF del 9-dic-2025). Incluye un año de salarios mínimos al alza.',
            self::FollowingYearsRate => 'Supuesto de la plataforma: la propuesta no se repite. Si lo capturas, es un aumento más cada año solo con la propuesta.',
        };
    }

    /** The day the platform set its proposal. */
    public function defaultSetOn(): string
    {
        return '2026-11-04';
    }

    /**
     * Every assumption at the platform's proposal, as a scenario keeps them: its value, where it comes from and when it was set.
     *
     * @return array<string, array{value_bp: int, source: string, set_on: string}>
     */
    public static function defaultSnapshot(): array
    {
        $snapshot = [];

        foreach (self::cases() as $key) {
            $snapshot[$key->value] = ['value_bp' => $key->defaultBasisPoints(), 'source' => $key->defaultSource(), 'set_on' => $key->defaultSetOn()];
        }

        return $snapshot;
    }
}
