import type { ChoiceOption, ReportingIssue } from '@/types';

/** What narrows the list besides its view: what was typed, and a work center. */
export type QualityFilters = { q: string; work_center: ChoiceOption | null };

/** The query of the list: always the view (the address names it), and only the filters that narrow. */
export function qualityQuery(
    filters: QualityFilters,
    issue: ReportingIssue['value'],
): Record<string, string> {
    const q = filters.q.trim();

    return {
        issue,
        ...(q === '' ? {} : { q }),
        ...(filters.work_center === null
            ? {}
            : { work_center: filters.work_center.value }),
    };
}

/** The views of the list as tabs: with their count, the open one marked, and what asks for action flagged, each with its query. */
export function qualityTabs(
    issues: ReportingIssue[],
    current: ReportingIssue['value'],
    filters: QualityFilters,
): {
    label: string;
    count: number;
    active: boolean;
    needsAction: boolean;
    query: Record<string, string>;
}[] {
    return issues.map((issue) => ({
        label: issue.label,
        count: issue.count,
        active: issue.value === current,
        needsAction: issue.pending,
        query: qualityQuery(filters, issue.value),
    }));
}

/** "Las 212 personas tienen su línea de reporte completa." */
export function orderText(people: number): string {
    return people === 1
        ? 'La única persona activa tiene su línea de reporte completa.'
        : `Las ${people} personas tienen su línea de reporte completa.`;
}
