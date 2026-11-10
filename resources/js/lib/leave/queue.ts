import { countOf } from '@/lib/plural';
import type { LeaveQueueFilters, LeaveQueueRequest } from '@/types';

/** The query string of RH's queue: only what narrows it, so the first tab with no filters has a plain address. */
export function queueQuery(
    filters: LeaveQueueFilters,
): Partial<Record<keyof LeaveQueueFilters, string>> {
    const q = filters.q.trim();

    return {
        ...(filters.tab === 'attend' ? {} : { tab: filters.tab }),
        ...(q === '' ? {} : { q }),
        ...(filters.center === null ? {} : { center: filters.center.value }),
        ...(filters.area === null ? {} : { area: filters.area.value }),
    };
}

/** What RH reads of the days of a request: whether the person had them, from which periods, or how many were missing. */
export function daysLine(
    request: Pick<LeaveQueueRequest, 'hadBalance' | 'periods' | 'shortBy'>,
): string {
    if (request.hadBalance === null) {
        return 'No usa días de vacaciones';
    }

    if (request.hadBalance) {
        return request.periods.length === 0
            ? 'Tenía días'
            : `Tenía días · del periodo ${request.periods.join(' y ')}`;
    }

    return `No tenía días · ${request.shortBy === 0 ? 'sin saldo al pedirla' : `le faltaban ${countOf(request.shortBy, 'día', 'días')}`}`;
}
