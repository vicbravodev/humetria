import { usePage } from '@inertiajs/react';
import climate from '@/routes/tenant/climate';
import pulse from '@/routes/tenant/pulse';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { engagementTrail } from '@/lib/engagement/trail';
import type { BreadcrumbItem } from '@/types';

/** The survey kind of the page: the pages of Clima and of Pulso are the same ones, each with its own routes. */
export function useEngagementKind(): 'climate' | 'pulse' {
    return usePage().props['kind'] === 'pulse' ? 'pulse' : 'climate';
}

/** The routes of the kind of survey the page is showing (`tenant.climate.*` or `tenant.pulse.*`). */
export function useEngagementRoutes(): typeof climate | typeof pulse {
    return useEngagementKind() === 'pulse' ? pulse : climate;
}

/** What the tool is called in the interface. */
export function useEngagementName(): 'Clima' | 'Pulso' {
    return useEngagementKind() === 'pulse' ? 'Pulso' : 'Clima';
}

/** The breadcrumbs of the page: «Escucha a tu gente › Clima|Pulso › …», the tool linking to its home. */
export function useEngagementTrail(
    ...rest: BreadcrumbItem[]
): BreadcrumbItem[] {
    const tenantSlug = useTenantSlug();

    return engagementTrail(
        useEngagementName(),
        useEngagementRoutes().index.url(tenantSlug),
        ...rest,
    );
}

/** The crumb of one survey, linking to its page. */
export function useSurveyCrumb(survey: {
    id: string;
    name: string;
}): BreadcrumbItem {
    return {
        title: survey.name,
        href: useEngagementRoutes().show.url([useTenantSlug(), survey.id]),
    };
}
