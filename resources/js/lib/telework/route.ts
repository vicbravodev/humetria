import { STAGES } from '@/lib/compliance-routes';
import type {
    RouteAccidents,
    RouteProgress,
    RouteRow,
    RouteStageState,
} from '@/types';
import { shortDay } from '@/lib/telework/accident';

export type RouteStage = {
    number: number;
    title: string;
    text: string;
    state: RouteStageState;
    rows: RouteRow[];
    /** What a done stage says in one line. */
    summary: string;
};

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
    const current = route.done.indexOf(false);

    return STAGES.nom037.map((stage, index) => ({
        number: index + 1,
        title: stage.title,
        text: stage.text,
        state:
            route.done[index] === true
                ? 'done'
                : index === current
                  ? 'current'
                  : 'pending',
        rows: rows.filter((row) => row.stage === index + 1),
        summary: SUMMARIES[index] ?? '',
    }));
}

/** «Si hoy llegara una inspección»: what the center could show of what applies, and where it gets by ending its stage. */
export function inspectionReach(
    rows: RouteRow[],
    stage: RouteStage | undefined,
): { ready: number; applicable: number; afterStage: number } {
    const applicable = rows.filter((row) => row.state !== 'not_applicable');
    const ready = applicable.filter((row) => row.state === 'done').length;
    const left =
        stage === undefined
            ? 0
            : stage.rows.filter(
                  (row) =>
                      row.state !== 'done' && row.state !== 'not_applicable',
              ).length;

    return { ready, applicable: applicable.length, afterStage: ready + left };
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

/** Where a stage is done, opened from its line: the first of its rows the person may open. */
export function stageHref(stage: RouteStage): string | null {
    return stage.rows.find((row) => row.href !== null)?.href ?? null;
}

/** A done stage folded into one line: what was done, and where to see it. */
export function stageDone(stage: RouteStage): {
    summary: string;
    href: string | null;
    action: string;
} {
    return {
        summary: stage.summary,
        href: stageHref(stage),
        action: stage.rows.find((row) => row.href !== null)?.action ?? 'Ver',
    };
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
