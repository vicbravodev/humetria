import { STAGES } from '@/lib/compliance-routes';
import { countOf, plural } from '@/lib/plural';
import { stagesOf } from '@/lib/route-stages';
import type { RouteStage } from '@/lib/route-stages';
import type { RouteProgress, RouteRow } from '@/types';

/** The seat reports of a center nobody resolved in time: how many, since when the oldest waits, and where they are. */
export type LateReports = {
    count: number;
    /** Y-m-d. */
    since: string;
    waiting: string;
    href: string;
};

/** How many of the people who work standing answered their two questions in the portal. */
export type PeopleInputs = { answered: number; people: number };

const SUMMARIES = [
    'Hecho: cada puesto de pie registrado con su Figura 1',
    'Hecho: asientos en cada puesto y áreas de descanso señalizadas',
    'Hecho: cada persona evaluada, en un acta y enterada',
    'Hecho: medidas y pausas acordadas',
    'Hecho: reglamento firmado, depositado y repartido',
    'Hecho: tu expediente está completo',
];

/** The six stages of the Ley Silla route of a center (board V2-Ruta-Silla), each with its requirements. */
export function routeStages(
    route: RouteProgress,
    rows: RouteRow[],
): RouteStage[] {
    return stagesOf(
        STAGES.seating.map((stage, index) => ({
            ...stage,
            summary: SUMMARIES[index] ?? '',
        })),
        route,
        rows,
    );
}

/** The line under the title: who works standing in how many positions, the six stages, and how many are done. */
export function routeIntro(
    center: string,
    { people, positions }: { people: number; positions: number },
    route: RouteProgress,
): string {
    const who = `${countOf(people, 'persona', 'personas')} ${plural(people, 'trabaja', 'trabajan')} de pie en ${countOf(positions, 'puesto', 'puestos')}`;
    const done = route.done.filter(Boolean).length;
    const finished =
        done === 0 ? 'Empieza por la primera.' : `Ya terminaste ${done}.`;

    return `En ${center} ${who}. Estas son las 6 etapas, en orden. ${finished}`;
}

/** «Antes de seguir»: the seat reports nobody resolved, and since when the oldest waits. */
export function reportsLine(reports: LateReports): string {
    return reports.count === 1
        ? `Una persona avisó de un asiento dañado. Espera respuesta desde ${reports.waiting}.`
        : `${reports.count} avisos de asientos dañados siguen abiertos. El más antiguo espera respuesta desde ${reports.waiting}.`;
}

/** «Las personas también participan»: how many answered their two questions. */
export function inputsLine(inputs: PeopleInputs): string {
    return `${inputs.answered} de ${inputs.people} ya ${plural(inputs.answered, 'contestó', 'contestaron')} sus 2 preguntas sobre calzado y molestias.`;
}
