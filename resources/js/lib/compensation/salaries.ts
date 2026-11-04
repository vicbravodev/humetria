import { countOf, plural } from '@/lib/plural';

/** How the tabulator and the individual salaries are narrowed, as the server sends it back. */
export type SalaryFilters = { q: string };

/** Where a salary stands today: the one in force, one that starts later or one already replaced. */
export type SalaryStanding = 'current' | 'upcoming' | 'past';

/** The query string for the filters: only what narrows the list, so a plain list has a plain address. */
export function salaryQuery(
    filters: SalaryFilters,
): Partial<Record<keyof SalaryFilters, string>> {
    const q = filters.q.trim();

    return q === '' ? {} : { q };
}

/** "12 categorías y niveles." */
export function tabulatorHeadline(total: number): string {
    if (total === 0) {
        return 'Todavía no hay categorías.';
    }

    return `${countOf(total, 'categoría', 'categorías')} y ${plural(total, 'nivel', 'niveles')}.`;
}

/** "Operador · A". */
export function gradeName(grade: { category: string; level: string }): string {
    return `${grade.category} · ${grade.level}`;
}

/**
 * Where a salary stands given the vigencia of the one in force today (`Y-m-d` dates compare as text): the same one is in
 * force, a later one is upcoming and an earlier one has been replaced.
 */
export function salaryStanding(
    effectiveFrom: string,
    currentFrom: string | null,
): SalaryStanding {
    if (effectiveFrom === currentFrom) {
        return 'current';
    }

    return currentFrom === null || effectiveFrom > currentFrom
        ? 'upcoming'
        : 'past';
}
