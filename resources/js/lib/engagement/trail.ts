import type { BreadcrumbItem } from '@/types';

/**
 * The breadcrumbs of a page of Clima or Pulso: their family first, as the sidebar groups them, then the tool (a link
 * when something follows it) and the rest.
 */
export function engagementTrail(
    tool: string,
    toolHref: string,
    ...rest: BreadcrumbItem[]
): BreadcrumbItem[] {
    return [
        { title: 'Escucha a tu gente' },
        rest.length === 0 ? { title: tool } : { title: tool, href: toolHref },
        ...rest,
    ];
}
