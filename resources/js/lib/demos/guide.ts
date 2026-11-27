import type { DemoGuide, DemoGuideStep } from '@/types/demo-guide';

/** The control a step lights up, only while its page is the one open (the address without its query). */
export function spotlightFor(
    current: DemoGuideStep,
    url: string,
): { selector: string } | null {
    if (current.target === null) {
        return null;
    }

    const page = new URL(current.href, 'http://localhost').pathname;

    return url.split('?')[0] === page
        ? { selector: `[data-tour="${current.target}"]` }
        : null;
}

/** «Paso 2 de 6 · NOM-035»: the step within its tour. */
export function progressLabel(current: DemoGuideStep): string {
    return `Paso ${current.index} de ${current.total} · ${current.tour}`;
}

/** «4 de 15»: the step the session is on over all its steps (done or skipped come before it). */
export function guideCount(guide: DemoGuide): string {
    const done = guide.tours.reduce((sum, tour) => sum + tour.done, 0);
    const total = guide.tours.reduce((sum, tour) => sum + tour.total, 0);

    return `${Math.min(done + (guide.finished ? 0 : 1), total)} de ${total}`;
}

/**
 * The count in a step's live sentence («Llegaron 3 de 5 respuestas», «4 marcas en el centro»), with its noun said for that
 * count. ponytail: it reads the server's fixed copy (Demos\Support\Tours); a new live sentence shape needs a case here.
 */
export function liveCount(
    live: string | null,
): { count: number; total: number | null; noun: string } | null {
    const found = live?.match(/(\d+)(?: de (\d+))? (\p{L}+)/u);

    if (found === null || found === undefined) {
        return null;
    }

    const count = Number(found[1]);
    const noun = found[3] ?? '';

    return {
        count,
        total: found[2] === undefined ? null : Number(found[2]),
        noun: count === 1 ? noun.replace(/s$/u, '') : noun,
    };
}

/** «Seguir con 3 respuestas»: on a step the audience does, moving on with what already came in; null when there is none. */
export function continueLabel(current: DemoGuideStep): string | null {
    const live = current.kind === 'watch' ? liveCount(current.live) : null;

    return live === null || live.count === 0
        ? null
        : `Seguir con ${live.count} ${live.noun}`;
}

/** The line that celebrates: the end of the session, or the step just done; null when there is nothing to celebrate. */
export function celebrationOf(guide: DemoGuide): string | null {
    if (guide.finished) {
        return guide.tours.length === 1
            ? 'Terminaste el recorrido de esta sesión.'
            : `Terminaste los ${guide.tours.length} recorridos de esta sesión.`;
    }

    return guide.justDone === null ? null : `Listo: «${guide.justDone}».`;
}

/** A celebration held on screen, with the step the guide was on when it came. */
export type Celebration = { text: string; key: string | null };

/** What to hold once `guide` arrives: a new celebration replaces the one held; a guide with none keeps it. */
export function nextCelebration(
    held: Celebration | null,
    guide: DemoGuide | null,
): Celebration | null {
    const text = guide === null ? null : celebrationOf(guide);
    const key = guide?.current?.key ?? null;

    return text === null || (held?.text === text && held.key === key)
        ? held
        : { text, key };
}

/** The held celebration, while the guide stays on the step it came with (the next poll brings none). */
export function shownCelebration(
    held: Celebration | null,
    guide: DemoGuide | null,
): string | null {
    return held !== null &&
        guide !== null &&
        held.key === (guide.current?.key ?? null)
        ? held.text
        : null;
}

function isRecord(value: unknown): value is Record<string, unknown> {
    return typeof value === 'object' && value !== null;
}

function hasStrings(value: Record<string, unknown>, keys: string[]): boolean {
    return keys.every((key) => typeof value[key] === 'string');
}

function hasNumbers(value: Record<string, unknown>, keys: string[]): boolean {
    return keys.every((key) => typeof value[key] === 'number');
}

function orString(value: unknown): boolean {
    return value === null || typeof value === 'string';
}

function isTour(value: unknown): boolean {
    return (
        isRecord(value) &&
        hasStrings(value, ['key', 'title']) &&
        hasNumbers(value, ['done', 'total'])
    );
}

function isStep(value: unknown): boolean {
    return (
        isRecord(value) &&
        hasStrings(value, ['key', 'tour', 'title', 'todo', 'why', 'href']) &&
        hasNumbers(value, ['index', 'total']) &&
        (value['kind'] === 'do' || value['kind'] === 'watch') &&
        [value['target'], value['ask'], value['live']].every(orString)
    );
}

/** Whether what the poll brought is the guide; anything else (no session, a login page) means no guide. */
export function isDemoGuide(value: unknown): value is DemoGuide {
    return (
        isRecord(value) &&
        Array.isArray(value['tours']) &&
        value['tours'].every(isTour) &&
        (value['current'] === null || isStep(value['current'])) &&
        typeof value['finished'] === 'boolean' &&
        orString(value['justDone'])
    );
}
