import { index } from '@/routes/tenant/campaigns';
import { route } from '@/routes/tenant/nom035';
import type { BreadcrumbItem } from '@/types';

/**
 * The breadcrumbs of a NOM-035 page of a center (rediseño v2): NOM-035 opens its centers, a center its route (board
 * V2-Ruta-N035), so a page entered from a stage leads back to it. Then the page itself.
 */
export function nom035Crumbs(
    tenantSlug: string,
    workCenter: { id: string; name: string },
    ...tail: BreadcrumbItem[]
): BreadcrumbItem[] {
    return [
        { title: 'NOM-035', href: index(tenantSlug) },
        {
            title: workCenter.name,
            href: route([tenantSlug, workCenter.id]),
        },
        ...tail,
    ];
}
