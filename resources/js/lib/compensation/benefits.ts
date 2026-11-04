import { toCalendarDate } from '@/lib/calendar-date';
import { countOf, plural } from '@/lib/plural';

/** How the benefits list is narrowed, as the server sends it back. */
export type BenefitFilters = { q: string; status: 'active' | 'archived' };

/** The whole catalog at a glance, whatever the search. */
export type BenefitCounts = { active: number; archived: number };

/** A row of a seniority table as it is typed: the years from which it applies and the value. */
export type TenureFormRow = { from_years: string; value: string };

type StoredTenureRow = {
    from_years: number;
    value: string;
    value_text: string;
};

type Grade = { value: string; label: string };

/** The query string for the filters: only what narrows the list, so a plain list has a plain address. */
export function benefitQuery(
    filters: BenefitFilters,
): Partial<Record<keyof BenefitFilters, string>> {
    const q = filters.q.trim();

    return {
        ...(q === '' ? {} : { q }),
        ...(filters.status === 'archived' ? { status: 'archived' } : {}),
    };
}

/** "11 prestaciones activas y 1 archivada." */
export function benefitsHeadline({ active, archived }: BenefitCounts): string {
    if (active === 0 && archived === 0) {
        return 'Todavía no hay prestaciones.';
    }

    const actives = `${countOf(active, 'prestación', 'prestaciones')} ${plural(active, 'activa', 'activas')}`;

    return archived === 0
        ? `${actives}.`
        : `${actives} y ${countOf(archived, 'archivada', 'archivadas')}.`;
}

/** The first day of the year of a date, the usual start of the terms of a contract. */
export function yearStart(now: Date): string {
    return toCalendarDate(new Date(now.getFullYear(), 0, 1));
}

/** The rows of a term's table as the form types them; none for a term without a table. */
export function tenureRowsOf(table: StoredTenureRow[] | null): TenureFormRow[] {
    return (table ?? []).map((row) => ({
        from_years: String(row.from_years),
        value: row.value,
    }));
}

/** One more row, starting at the year after the last one when that is a number. */
export function addTenureRow(rows: TenureFormRow[]): TenureFormRow[] {
    const last = rows.at(-1);
    const next = last === undefined ? 1 : Number(last.from_years) + 1;

    return [
        ...rows,
        {
            from_years: Number.isInteger(next) ? String(next) : '',
            value: '',
        },
    ];
}

export function removeTenureRow(
    rows: TenureFormRow[],
    index: number,
): TenureFormRow[] {
    return rows.filter((_, position) => position !== index);
}

export function setTenureRow(
    rows: TenureFormRow[],
    index: number,
    change: Partial<TenureFormRow>,
): TenureFormRow[] {
    return rows.map((row, position) =>
        position === index ? { ...row, ...change } : row,
    );
}

/** The grades chosen with one more; a grade already there, or the empty "all" option, changes nothing. */
export function addGrade(grades: Grade[], grade: Grade): Grade[] {
    return grade.value === '' || grades.some((g) => g.value === grade.value)
        ? grades
        : [...grades, grade];
}

export function removeGrade(grades: Grade[], value: string): Grade[] {
    return grades.filter((grade) => grade.value !== value);
}
