import { countOf } from '@/lib/plural';
import type { LeavePolicyRowData } from '@/types';

/** A row of the policy as its fields hold it: text, so a field can be empty or half typed. */
export type PolicyRowDraft = {
    /** Only to tell the rows apart on the page. */
    key: number;
    kind: 'tier' | 'fixed';
    label: string;
    yearFrom: string;
    yearTo: string;
    days: string;
};

/** The days the law gives for the year typed (a whole number from 1); the last year the table holds for a longer service. */
export function floorFor(floors: number[], year: string): number | null {
    const years = Number(year);

    if (!Number.isInteger(years) || years < 1) {
        return null;
    }

    return floors[Math.min(years, floors.length) - 1] ?? null;
}

/** "Mínimo legal: 20 días" next to the last year of a range; empty while the range is not known. */
export function floorText(floors: number[], yearTo: string): string {
    const floor = floorFor(floors, yearTo);

    return floor === null
        ? ''
        : `Mínimo legal: ${countOf(floor, 'día', 'días')}`;
}

/** A new row: a tier starts on the year after the last one the others reach; days that come every year have no years. */
export function blankRow(
    kind: PolicyRowDraft['kind'],
    key: number,
    rows: PolicyRowDraft[],
): PolicyRowDraft {
    const last = Math.max(
        0,
        ...rows
            .filter((row) => row.kind === 'tier')
            .map((row) => Number(row.yearTo) || 0),
    );

    return {
        key,
        kind,
        label: '',
        yearFrom: kind === 'tier' ? String(last + 1) : '',
        yearTo: '',
        days: '',
    };
}

export function draftsOf(rows: LeavePolicyRowData[]): PolicyRowDraft[] {
    return rows.map((row, key) => ({
        key,
        kind: row.kind,
        label: row.label ?? '',
        yearFrom: row.yearFrom === null ? '' : String(row.yearFrom),
        yearTo: row.yearTo === null ? '' : String(row.yearTo),
        days: String(row.days),
    }));
}

/** «1 día fijo cada año», «3 días fijos cada año»: days that come every year whatever the seniority. */
export function fixedDaysLine(days: number): string {
    return `${days} ${days === 1 ? 'día fijo' : 'días fijos'} cada año`;
}
