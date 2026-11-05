import { index, route } from '@/routes/tenant/seating';
import type { BreadcrumbItem } from '@/types';

/**
 * The breadcrumbs of a Ley Silla page (rediseño v2): Ley Silla opens the centers, a center its route (board V2-Ruta-Silla),
 * so every page that stays is entered from, and leads back to, its stage. Then the page itself.
 */
export function seatingCrumbs(
    tenantSlug: string,
    workCenter: { id: string; name: string },
    ...tail: BreadcrumbItem[]
): BreadcrumbItem[] {
    return [
        { title: 'Ley Silla', href: index(tenantSlug) },
        { title: workCenter.name, href: route([tenantSlug, workCenter.id]) },
        ...tail,
    ];
}
