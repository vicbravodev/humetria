import { index, route } from '@/routes/tenant/telework';
import type { BreadcrumbItem } from '@/types';

/**
 * The breadcrumbs of a NOM-037 page (rediseño v2): NOM-037 opens the centers, a center its route (board V2-Ruta-N037), so
 * every page that stays is entered from, and leads back to, its stage. Then the page itself.
 */
export function teleworkCrumbs(
    tenantSlug: string,
    workCenter: { id: string; name: string } | null,
    ...tail: BreadcrumbItem[]
): BreadcrumbItem[] {
    return [
        { title: 'NOM-037', href: index(tenantSlug) },
        ...(workCenter === null
            ? []
            : [
                  {
                      title: workCenter.name,
                      href: route([tenantSlug, workCenter.id]),
                  },
              ]),
        ...tail,
    ];
}
