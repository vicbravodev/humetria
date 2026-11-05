import { STAGES } from '@/lib/compliance-routes';
import { stagesOf } from '@/lib/route-stages';
import type { RouteStage } from '@/lib/route-stages';
import type { RouteAccidents, RouteProgress, RouteRow } from '@/types';
import { shortDay } from '@/lib/telework/accident';

export type { RouteStage } from '@/lib/route-stages';
export { inspectionReach, stageDone, stageHref } from '@/lib/route-stages';

/** The documents of the first stage besides the policy (5.3, 5.6, 5.8, 5.10, 5.13 and 5.14). */
const POLICY = '5.2';

const SUMMARIES = [
    'Hecho: política y documentos de apoyo vigentes',
    'Hecho: cada persona con su lugar convenido',
    'Hecho: cada lugar validado por la Comisión',
    'Hecho: equipo entregado y capacitación vigente',
    'Hecho: cada aviso revisado',
    'Hecho: tu expediente está completo',
];

/** The six stages of the route of a center (board V2-Ruta-N037): done, the one it is in, or ahead, each with its rows. */
export function routeStages(
    route: RouteProgress,
    rows: RouteRow[],
): RouteStage[] {
    return stagesOf(
        STAGES.nom037.map((stage, index) => ({
            ...stage,
            summary: SUMMARIES[index] ?? '',
        })),
        route,
        rows,
    );
}

/** The first stage as tasks: the policy alone, then its supporting documents together (k of 6 ready). */
export function stageOneTasks(rows: RouteRow[]): {
    policy: RouteRow | undefined;
    documents: RouteRow[];
    ready: number;
} {
    const documents = rows.filter((row) => row.numeral !== POLICY);

    return {
        policy: rows.find((row) => row.numeral === POLICY),
        documents,
        ready: documents.filter((row) => row.state === 'done').length,
    };
}

/** «Antes de seguir»: who reported an accident and since when it waits. */
export function accidentsLine(accidents: RouteAccidents): string {
    const who =
        accidents.person === null
            ? `${accidents.count} personas avisaron un accidente`
            : `${accidents.person} avisó un accidente el ${shortDay(accidents.since)}`;

    return `${who}. ${accidents.count === 1 ? 'Espera' : 'El más antiguo espera'} respuesta desde ${accidents.waiting}.`;
}

/** The line under the title: who teleworks in the center, the six stages, and how many are done. */
export function routeIntro(
    center: string,
    teleworkers: number,
    route: RouteProgress,
): string {
    const people =
        teleworkers === 1
            ? '1 persona que trabaja'
            : `${teleworkers} personas que trabajan`;
    const done = route.done.filter(Boolean).length;
    const finished =
        done === 0 ? 'Empieza por la primera.' : `Ya terminaste ${done}.`;

    return `${center} tiene ${people} desde casa más del 40 % del tiempo. Estas son las 6 etapas que pide la NOM-037, en orden. ${finished}`;
}
