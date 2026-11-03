import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { center, plan, survey } from '@/routes/tenant/seating';
import { index as assessments } from '@/routes/tenant/seating/assessments';
import { index as documents } from '@/routes/tenant/seating/documents';
import { show as regulation } from '@/routes/tenant/seating/regulation';
import { index as reports } from '@/routes/tenant/seating/reports';
import { index as seats } from '@/routes/tenant/seating/seats';

/** Where each part of a work center lives, by the key the server gives it (positions is the center's own page). */
export function useSeatingHref(workCenterId: string): (key: string) => string {
    const tenant = useTenantSlug();
    const route = { tenant, workCenter: workCenterId };
    const hrefs: Record<string, string> = {
        positions: center(route).url,
        people: assessments(route).url,
        inventory: seats(route).url,
        reports: reports(route).url,
        plan: plan(route).url,
        regulation: regulation(route).url,
        documents: documents(route).url,
        survey: survey(route).url,
    };

    return (key) => hrefs[key] ?? hrefs['positions'] ?? '';
}
