import { STAGES } from '@/lib/compliance-routes';
import { countOf, plural } from '@/lib/plural';
import { stagesOf } from '@/lib/route-stages';
import type { RouteStage } from '@/lib/route-stages';
import type { RouteProgress, RouteRow } from '@/types';

/** The latest evaluation closed within the cycle: the day it closed and who answered the guide that measures it. */
export type ClosedEvaluation = {
    closedOn: string;
    answered: number;
    target: number;
};

const SUMMARIES = [
    'Hecho: tu política de prevención está vigente',
    'Hecho: la evaluación está vigente',
    'Hecho: nadie espera canalización',
    'Hecho: tus personas ya conocen los resultados',
    'Hecho: tu programa de intervención está al día',
    'Hecho: tu informe 7.7 está completo',
];

/** The six stages of the NOM-035 route of a center (board V2-Ruta-N035), each with its requirement. */
export function routeStages(
    route: RouteProgress,
    rows: RouteRow[],
): RouteStage[] {
    return stagesOf(
        STAGES.nom035.map((stage, index) => ({
            ...stage,
            // A stage that does not apply, or one the person may not see (the channeling), says so instead.
            summary:
                rows.find((row) => row.stage === index + 1)?.detail ??
                SUMMARIES[index] ??
                '',
        })),
        route,
        rows,
    );
}

/** The line under the title: the last evaluation and that evaluating is only a part; before one, who works there. */
export function routeIntro(
    center: string,
    people: number,
    evaluation: ClosedEvaluation | null,
): string {
    if (evaluation === null) {
        return `En ${center} ${plural(people, 'trabaja', 'trabajan')} ${countOf(people, 'persona', 'personas')}. Estas son las 6 etapas de la NOM-035, en orden: evaluar es solo una parte.`;
    }

    const who =
        evaluation.target === 0
            ? ''
            : ` con ${evaluation.answered} de ${countOf(evaluation.target, 'persona', 'personas')}`;

    return `La evaluación de ${center} cerró el ${evaluation.closedOn}${who}. Evaluar es solo una parte: la norma también pide una política, compartir los resultados y actuar sobre los riesgos.`;
}

/** «Si hoy llegara una inspección, te faltaría»: what is missing, by name. */
export function inspectionGaps(rows: RouteRow[]): string[] {
    return rows
        .filter((row) => row.state === 'missing' || row.state === 'partial')
        .map((row) => row.title);
}
