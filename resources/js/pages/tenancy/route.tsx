import { Head, setLayoutProps } from '@inertiajs/react';
import RouteDone from '@/components/tenancy/route-done';
import RouteInProgress from '@/components/tenancy/route-in-progress';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { normOf } from '@/lib/compliance-routes';
import type { NormKey } from '@/lib/compliance-routes';
import { dossierHref } from '@/lib/route-links';
import { dashboard } from '@/routes/tenant';
import { index as campaigns } from '@/routes/tenant/campaigns';
import type { RouteProgress, Urgency } from '@/types';

type Props = {
    norm: NormKey;
    workCenter: { id: string; name: string };
    route: RouteProgress;
    urgencies: Urgency[];
    /** NOM-035: the end of the two-year cycle, "29 oct 2028". */
    until: string | null;
};

/** The route of one standard in one center (boards V3-Movil and V3-AlDia): on its way, or all done. */
export default function TenancyRoute(props: Props) {
    const tenantSlug = useTenantSlug();
    const norm = normOf(props.norm);
    const done = props.route.applies && !props.route.done.includes(false);
    setLayoutProps({
        breadcrumbs: [
            { title: 'Panel', href: dashboard(tenantSlug) },
            { title: norm.title },
            { title: props.workCenter.name },
        ],
    });

    return (
        <>
            <Head title={`${norm.title} · ${props.workCenter.name}`} />
            <div className="mx-auto flex w-full max-w-[1180px] flex-col gap-6 px-4 py-6 md:px-12 md:py-10">
                {done ? (
                    <RouteDone
                        norm={norm}
                        until={props.until}
                        dossierHref={dossierHref(
                            norm.key,
                            tenantSlug,
                            props.workCenter.id,
                        )}
                        evaluationsHref={campaigns(tenantSlug)}
                    />
                ) : (
                    <RouteInProgress
                        norm={norm}
                        workCenter={props.workCenter}
                        route={props.route}
                        urgencies={props.urgencies}
                    />
                )}
            </div>
        </>
    );
}
