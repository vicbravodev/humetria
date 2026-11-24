import type { CorrectionStatus } from '@/types';

const MONTHS = [
    'ene',
    'feb',
    'mar',
    'abr',
    'may',
    'jun',
    'jul',
    'ago',
    'sep',
    'oct',
    'nov',
    'dic',
];

/**
 * The queue's search as the server reads it: the text, only when there is some; the tab, unless it is the first; and, in the
 * tab of the marks made without a connection, the filter of those with no flags.
 */
export function correctionsQuery(filters: {
    q: string;
    status: string;
    flags?: string;
}): Partial<Record<'q' | 'status' | 'flags', string>> {
    const q = filters.q.trim();

    return {
        ...(filters.status === 'pending' ? {} : { status: filters.status }),
        ...(q === '' ? {} : { q }),
        ...(filters.status === 'offline' && filters.flags === 'none'
            ? { flags: 'none' }
            : {}),
    };
}

/** «10 nov · 09:00», from the server's local «Y-m-d H:i». */
export function shortMoment(local: string): string {
    const [date = '', time = ''] = local.split(' ');
    const [, month = 1, day = 1] = date.split('-').map(Number);

    return `${day} ${MONTHS[month - 1] ?? ''} · ${time}`;
}

/** How long a request waits: «hoy», «ayer», «hace 3 días». */
export function waitingFor(days: number): string {
    if (days <= 0) {
        return 'hoy';
    }

    return days === 1 ? 'ayer' : `hace ${days} días`;
}

/** The way from an empty queue to the requests already resolved: the tab to open and what its button says; none if no request was ever resolved. */
export function resolvedShortcut(counts: Record<CorrectionStatus, number>): {
    status: 'approved' | 'rejected';
    label: string;
} | null {
    const total = counts.approved + counts.rejected;

    if (total === 0) {
        return null;
    }

    return {
        status: counts.approved > 0 ? 'approved' : 'rejected',
        label: total === 1 ? 'Ver la resuelta' : `Ver las ${total} resueltas`,
    };
}
