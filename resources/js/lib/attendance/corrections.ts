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

/** The queue's search as the server reads it: only the text, and only when there is some. */
export function correctionsQuery(filters: {
    q: string;
    status: string;
}): Partial<Record<'q' | 'status', string>> {
    const q = filters.q.trim();

    return {
        ...(filters.status === 'pending' ? {} : { status: filters.status }),
        ...(q === '' ? {} : { q }),
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
