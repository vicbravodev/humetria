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

/**
 * The days the policy gives in years 1 to 6 as its rows stand now (half typed ones count for nothing): the tier of the year
 * or the law, never under the law, plus the fixed days that come every year.
 */
export function previewDays(
    rows: PolicyRowDraft[],
    floors: number[],
): number[] {
    const fixed = rows
        .filter((row) => row.kind === 'fixed')
        .reduce((total, row) => total + (Number(row.days) || 0), 0);

    return [1, 2, 3, 4, 5, 6].map((year) => {
        const law = floors[year - 1] ?? 0;
        const tier = rows.find(
            (row) =>
                row.kind === 'tier' &&
                Number(row.yearFrom) >= 1 &&
                Number(row.yearFrom) <= year &&
                year <= Number(row.yearTo) &&
                Number(row.days) > 0,
        );

        return Math.max(law, Number(tier?.days ?? 0)) + fixed;
    });
}

/** What the fixed bar of the editor says: nothing to save, something to save, or how many fields to fix first. */
export function saveBarText({
    dirty,
    errors,
}: {
    dirty: boolean;
    errors: number;
}): string {
    if (errors > 0) {
        return `Corrige ${countOf(errors, 'campo', 'campos')} para guardar`;
    }

    return dirty ? 'Hay cambios sin guardar' : 'Sin cambios por guardar';
}
