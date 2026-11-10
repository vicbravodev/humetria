import { countOf } from '@/lib/plural';
import type { ReportingIssue } from '@/types';

/** How many people are in a list with something to fix; one person at the top is not. */
export function qualityPending(issues: ReportingIssue[]): number {
    return issues
        .filter((issue) => issue.pending)
        .reduce((total, issue) => total + issue.count, 0);
}

/** "6 personas por revisar en la línea de reporte." */
export function qualityHeadline(issues: ReportingIssue[]): string {
    const pending = qualityPending(issues);

    return pending === 0
        ? 'La línea de reporte está completa.'
        : `${countOf(pending, 'persona por revisar', 'personas por revisar')} en la línea de reporte.`;
}
