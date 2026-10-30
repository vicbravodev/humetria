import type { WorkCenterEvaluationStanding } from '@/types';

/** How many people work in a work center: "Sin personal", "1 persona", "48 personas". */
export function staffText(employees: number): string {
    if (employees === 0) {
        return 'Sin personal';
    }

    return employees === 1 ? '1 persona' : `${employees} personas`;
}

/** The NOM-035 guides that apply, written for display; null when none does (nobody works there yet). */
export function guidesText(guides: string[]): string | null {
    if (guides.length === 0) {
        return null;
    }

    return guides.length === 1
        ? `NOM-035: Guía ${guides.join('')}`
        : `NOM-035: Guías ${guides.slice(0, -1).join(', ')} y ${guides.at(-1) ?? ''}`;
}

/** The guides as a work center card shows them under "Guías NOM-035": "I y III", or a dash when none applies. */
export function guidesShort(guides: string[]): string {
    return guidesText(guides)?.replace(/^NOM-035: Guías? /, '') ?? '—';
}

export type EvaluationStandingText = {
    badge: string;
    variant: 'default' | 'secondary' | 'success' | 'warning' | 'destructive';
    /** What "Siguiente evaluación" says. */
    next: string;
};

/** Where a work center stands with its NOM-035 evaluations (num. 7.9), as its card says it. */
export function evaluationStanding(
    evaluation: WorkCenterEvaluationStanding | null,
    employees: number,
): EvaluationStandingText {
    if (evaluation === null) {
        return employees === 0
            ? {
                  badge: 'Sin personal',
                  variant: 'secondary',
                  next: 'Al cargar personas',
              }
            : {
                  badge: 'Sin evaluar',
                  variant: 'warning',
                  next: 'Por programar',
              };
    }

    const day = evaluation.day ?? '';
    const standings: Record<
        WorkCenterEvaluationStanding['state'],
        EvaluationStandingText
    > = {
        running: {
            badge: 'Evaluación en curso',
            variant: 'default',
            next: `En curso, cierra el ${day}`,
        },
        draft: {
            badge: 'Evaluación en preparación',
            variant: 'secondary',
            next: 'En preparación',
        },
        due: { badge: 'Al día', variant: 'success', next: `Antes del ${day}` },
        overdue: {
            badge: 'Evaluación vencida',
            variant: 'destructive',
            next: `Venció el ${day}`,
        },
    };

    return standings[evaluation.state];
}
