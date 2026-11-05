import { index } from '@/routes/tenant/campaigns';
import { index as disclosure } from '@/routes/tenant/disclosure';
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

/**
 * The breadcrumbs of a page of the disclosure (boards V4-Difusion and V4-Difusion-Registro): NOM-035, «Difusión» (the
 * matrix of what the people must know), then the page itself.
 */
export function disclosureCrumbs(
    tenantSlug: string,
    ...tail: BreadcrumbItem[]
): BreadcrumbItem[] {
    return [
        { title: 'NOM-035', href: index(tenantSlug) },
        { title: 'Difusión', href: disclosure(tenantSlug) },
        ...tail,
    ];
}
