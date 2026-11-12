import { countOf } from '@/lib/plural';
import type { OrgChartFilters, OrgNode, PortalTeamPerson } from '@/types';

/** "3 directos · 21 en total" */
export function teamText({
    direct_reports,
    total_reports,
}: Pick<OrgNode, 'direct_reports' | 'total_reports'>): string {
    return `${countOf(direct_reports, 'directo', 'directos')} · ${total_reports} en total`;
}

/** "Ventas · Planta Norte", with what is known. */
export function placeText({
    area,
    work_center,
}: Pick<OrgNode, 'area' | 'work_center'>): string {
    return [area, work_center].filter((part) => part !== '').join(' · ');
}

/** The query of the page: only what narrows the tree and the focused person, so a plain tree has a plain address. */
export function orgChartQuery(
    filters: OrgChartFilters,
    focus?: string,
): Record<string, string> {
    return {
        ...(filters.work_center === null
            ? {}
            : { work_center: filters.work_center.value }),
        ...(filters.area === null ? {} : { area: filters.area.value }),
        ...(focus === undefined ? {} : { focus }),
    };
}

/** "Vendedor · Ventas", with what is known. */
export function teamPersonText({
    job_title,
    area,
}: Pick<PortalTeamPerson, 'job_title' | 'area'>): string {
    return [job_title, area].filter((part) => part !== '').join(' · ');
}
