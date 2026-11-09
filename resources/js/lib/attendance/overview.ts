import { duration } from '@/lib/attendance/week-text';

export type OverviewFilters = {
    q: string;
    status: string;
    /** The Monday of the week shown; empty for the last one that ended. */
    week: string;
    /** The center the overview narrows to; empty for every center. */
    center: string;
};

/** The overview's address as the server reads it: only what differs from the default view. */
export function overviewQuery(
    filters: OverviewFilters,
): Partial<Record<keyof OverviewFilters, string>> {
    const q = filters.q.trim();

    return {
        ...(q === '' ? {} : { q }),
        ...(filters.status === 'all' ? {} : { status: filters.status }),
        ...(filters.week === '' ? {} : { week: filters.week }),
        ...(filters.center === '' ? {} : { center: filters.center }),
    };
}

const FLAGS: Record<string, string> = {
    day_over_12_hours: 'Un día de más de 12 h',
    daily_overtime_over_4: 'Más de 4 h extra en un día',
    overtime_days_over_4: 'Horas extra en más de 4 días',
    triple_cap_exceeded: 'Más de 4 h al triple en la semana',
};

/** A legal limit a week broke (arts. 66 and 68), as RH reads it in a row. */
export function flagLabel(flag: string): string {
    return FLAGS[flag] ?? 'Rebasó un tope legal';
}

/** «40 h», «8 h · 1 h 30 min extra», or «Sin horas» for a week with no marks. */
export function hoursText(ordinary: number, overtime: number): string {
    if (ordinary === 0 && overtime === 0) {
        return 'Sin horas';
    }

    return overtime === 0
        ? duration(ordinary)
        : `${duration(ordinary)} · ${duration(overtime)} extra`;
}

/** «45 h» and «5 h extra» for the two lines of a week in a row: all the hours worked, and apart the overtime. */
export function hoursSplit(
    ordinary: number,
    overtime: number,
): { total: string; extra: string | null } {
    if (ordinary === 0 && overtime === 0) {
        return { total: 'Sin horas', extra: null };
    }

    return {
        total: duration(ordinary + overtime),
        extra: overtime === 0 ? null : `${duration(overtime)} extra`,
    };
}

export type WeekBadge = {
    label: string;
    variant: 'success' | 'destructive' | 'warning' | 'secondary';
};

const BADGES: Record<string, WeekBadge> = {
    confirmed: { label: 'Confirmada', variant: 'success' },
    disputed: { label: 'Objetada', variant: 'destructive' },
};

/** Where a person stands with the week: silence is not conformity, and nobody answers a week still in course. */
export function weekBadge(status: string, over: boolean): WeekBadge {
    return (
        BADGES[status] ??
        (over
            ? { label: 'Sin confirmar', variant: 'warning' }
            : { label: 'En curso', variant: 'secondary' })
    );
}

/** What a person's week warns RH of: each limit broken, or else that its overtime nears the year's cap. */
export function weekNotes(person: {
    flags: string[];
    atRisk: boolean;
}): string[] {
    if (person.flags.length > 0) {
        return person.flags.map(flagLabel);
    }

    return person.atRisk ? ['Cerca del tope de horas extra'] : [];
}
