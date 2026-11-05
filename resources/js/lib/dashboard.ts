import { NORMS, STAGES } from '@/lib/compliance-routes';
import type { Norm, NormKey } from '@/lib/compliance-routes';
import { countOf } from '@/lib/plural';
import type {
    CenterRoutes,
    DashboardSummary,
    RouteProgress,
    StageState,
} from '@/types';

// Titles people put before their name; "Ma." is María, not a title, so it stays.
const TITLES = new Set([
    'dr',
    'dra',
    'lic',
    'ing',
    'mtro',
    'mtra',
    'arq',
    'prof',
    'profa',
    'sr',
    'sra',
    'srita',
    'c.p',
]);

/** "Hola, Rita": the first name only, past any title (Dr., Lic., Ing.). */
export function greeting(name: string): string {
    const first =
        name
            .trim()
            .split(/\s+/)
            .find(
                (word) =>
                    word !== '' &&
                    !TITLES.has(word.toLowerCase().replace(/\.$/, '')),
            ) ?? '';

    return first === '' ? 'Hola' : `Hola, ${first}`;
}

/** The sentence under the greeting (board V2-Panel). */
export function panelSummary({ norms, centers }: DashboardSummary): string {
    if (centers === 0) {
        return 'Aún no registras centros de trabajo.';
    }

    return `Llevas ${countOf(norms, 'norma', 'normas')} en ${countOf(centers, 'centro', 'centros')}. Primero lo urgente, luego dónde vas en cada una.`;
}

/** Each of the six stages: done, the earliest one left (where the center is) or still ahead. */
export function stageStates(route: RouteProgress): StageState[] {
    const current = route.done.indexOf(false);

    return route.done.map((done, index) =>
        done ? 'done' : index === current ? 'current' : 'pending',
    );
}

/** "Etapa 2 de 6 · 18 de 60 contestaron"; the line alone, capitalized, when it is all done or does not apply. */
export function stageLine(route: RouteProgress): string {
    const current = route.done.indexOf(false);

    if (!route.applies || current === -1) {
        return route.status.charAt(0).toUpperCase() + route.status.slice(1);
    }

    return `Etapa ${current + 1} de ${route.done.length} · ${route.status}`;
}

/** The standards that apply to some center of the page, each with every center of it (those it does not apply to say so). */
export function normsInUse(centers: CenterRoutes[]): {
    norm: Norm;
    rows: { id: string; name: string; route: RouteProgress }[];
}[] {
    return NORMS.filter((norm) =>
        centers.some((center) => center[norm.key].applies),
    ).map((norm) => ({
        norm,
        rows: centers.map((center) => ({
            id: center.id,
            name: center.name,
            route: center[norm.key],
        })),
    }));
}

export type NextStep = {
    norm: NormKey;
    /** 1 to 6. */
    stage: number;
    /** How many centers are at that stage of that route. */
    centers: number;
    title: string;
    text: string;
};

/**
 * "Siguiente paso recomendado": the earliest stage any center has left, of any route; on a tie, the first standard in
 * the menu's order. Null when every route that applies is done.
 */
export function nextStep(centers: CenterRoutes[]): NextStep | null {
    let best: NextStep | null = null;

    for (const norm of NORMS) {
        const stages = centers
            .map((center) => center[norm.key])
            .filter((route) => route.applies)
            .map((route) => route.done.indexOf(false))
            .filter((index) => index !== -1);
        const earliest = Math.min(...stages);
        const stage = STAGES[norm.key][earliest];

        if (
            stage !== undefined &&
            (best === null || earliest < best.stage - 1)
        ) {
            best = {
                norm: norm.key,
                stage: earliest + 1,
                centers: stages.filter((index) => index === earliest).length,
                ...stage,
            };
        }
    }

    return best;
}
