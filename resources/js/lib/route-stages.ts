import type { Stage } from '@/lib/compliance-routes';
import type { RouteProgress, RouteRow, RouteStageState } from '@/types';

/** A stage of the route of a center (boards V2-Ruta-N037 and V2-Ruta-Silla) with the rows it asks for. */
export type RouteStage = {
    number: number;
    title: string;
    text: string;
    state: RouteStageState;
    rows: RouteRow[];
    /** What a done stage says in one line. */
    summary: string;
};

/**
 * The six stages of a route: done, the one the center is in, or ahead, each with its rows and, once done, what it says.
 * `stages` pairs each stage with that one line.
 */
export function stagesOf(
    stages: (Stage & { summary: string })[],
    route: RouteProgress,
    rows: RouteRow[],
): RouteStage[] {
    const current = route.done.indexOf(false);

    return stages.map((stage, index) => ({
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
        summary: stage.summary,
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
