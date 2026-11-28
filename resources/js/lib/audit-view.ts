import type { AuditCounts, AuditEntryRow, AuditFamily } from '@/types';

export type AuditDay = { day: string; entries: AuditEntryRow[] };

export type AuditTag = {
    label: string;
    variant: 'default' | 'secondary' | 'success' | 'warning' | 'destructive';
};

/** The entries of a page under their local day, as the table groups them (tablero Bitácora); they come newest first. */
export function auditDays(entries: AuditEntryRow[]): AuditDay[] {
    const days: AuditDay[] = [];

    for (const entry of entries) {
        const last = days.at(-1);

        if (last?.day === entry.day) {
            last.entries.push(entry);
        } else {
            days.push({ day: entry.day, entries: [entry] });
        }
    }

    return days;
}

const KIND_TAGS: Record<Exclude<keyof AuditCounts, 'all'>, AuditTag> = {
    support: { label: 'Soporte', variant: 'warning' },
    billing: { label: 'Cobro o plan', variant: 'success' },
    results: { label: 'Resultados', variant: 'destructive' },
    roles: { label: 'Roles', variant: 'default' },
};

/** The tag before an entry's action: its view of the trail in its tone, or else the family it belongs to. */
export function auditTag(
    entry: AuditEntryRow,
    families: AuditFamily[],
): AuditTag {
    if (entry.kind !== null) {
        return KIND_TAGS[entry.kind];
    }

    const family = entry.event.slice(0, entry.event.indexOf('.'));

    return {
        label: families.find(({ value }) => value === family)?.label ?? 'Otro',
        variant: 'secondary',
    };
}

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

function day(value: string): string {
    const [year, month, date] = value.split('-');

    return `${Number(date)} ${MONTHS[Number(month) - 1] ?? ''} ${year ?? ''}`;
}

/** What the period chip says of the days the trail is narrowed to; null when it is not. */
export function periodChip(from: string, to: string): string | null {
    if (from === '' && to === '') {
        return null;
    }

    if (to === '') {
        return `Desde ${day(from)}`;
    }

    if (from === '') {
        return `Hasta ${day(to)}`;
    }

    return from === to ? day(from) : `${day(from)} – ${day(to)}`;
}
