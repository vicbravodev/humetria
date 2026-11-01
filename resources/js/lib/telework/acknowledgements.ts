import type { AckAllMatching, AckFilters, AckProgress } from '@/types';

/** The share of the people expected who confirmed, as a whole percent; nobody expected is 0, never NaN. */
export function percent(progress: AckProgress): number {
    return progress.expected === 0
        ? 0
        : Math.round((progress.acknowledged / progress.expected) * 100);
}

/** "12 de 40 (30 %)": the written count that goes next to the bar. */
export function progressText(progress: AckProgress): string {
    return `${progress.acknowledged} de ${progress.expected} (${percent(progress)} %)`;
}

/** The query of the list: only what differs from the default view, so a clean URL is the whole list. */
export function ackQuery(
    filters: AckFilters,
): Partial<Record<keyof AckFilters, string>> {
    const q = filters.q.trim();

    return {
        ...(filters.status === 'all' ? {} : { status: filters.status }),
        ...(q === '' ? {} : { q }),
    };
}

/**
 * The "all the pending ones found" choice, only while the list still shows what it was made on: the pending view with the
 * same search. Change either and the choice is gone, so what is submitted always matches what is on screen.
 */
export function liveAll(
    all: AckAllMatching | null,
    filters: AckFilters,
): AckAllMatching | null {
    return all !== null && filters.status === 'pending' && all.q === filters.q
        ? all
        : null;
}
