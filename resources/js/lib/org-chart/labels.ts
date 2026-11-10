import { countOf } from '@/lib/plural';
import type { OrgChartFilters, OrgNode } from '@/types';

/** "120 personas activas, 87 con jefe directo." */
export function orgChartHeadline(total: number, withManager: number): string {
    return total === 0
        ? 'Nadie activo por ahora.'
        : `${countOf(total, 'persona activa', 'personas activas')}, ${withManager === 0 ? 'ninguna' : withManager} con jefe directo.`;
}

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

/** "Ver 10 más": the reports still to load; without the count when a filter hides some of them. */
export function moreText(
    directReports: number,
    loaded: number,
    filtered: boolean,
): string {
    const left = directReports - loaded;

    return filtered || left <= 0 ? 'Ver más' : `Ver ${left} más`;
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
