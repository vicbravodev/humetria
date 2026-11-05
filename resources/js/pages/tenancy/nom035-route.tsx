import { Head, setLayoutProps } from '@inertiajs/react';
import RouteHumiCard from '@/components/humetria/route-humi-card';
import RouteInspectionCard from '@/components/humetria/route-inspection-card';
import Nom035InspectionGaps from '@/components/tenancy/nom035-inspection-gaps';
import Nom035RouteBefore from '@/components/tenancy/nom035-route-before';
import Nom035RouteStages from '@/components/tenancy/nom035-route-stages';
import RouteDone from '@/components/tenancy/route-done';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { normOf } from '@/lib/compliance-routes';
import { routeIntro, routeStages } from '@/lib/nom035/route';
import type { ClosedEvaluation } from '@/lib/nom035/route';
import { inspectionReach } from '@/lib/route-stages';
import { index } from '@/routes/tenant/campaigns';
import type { RouteProgress, RouteRow, Urgency } from '@/types';

/**
 * Board V2-Ruta-N035: the NOM-035 route of a center after evaluating, its six stages in order, who waits to be channeled
 * before them and how ready it is for an inspection; with the six done, «Todo al día» (board V3-AlDia).
 */
export default function Nom035Route({
    workCenter,
    people,
    evaluation,
    route,
    rows,
    urgencies,
    until,
    inspectionHref,
    dossierHref,
}: {
    workCenter: { id: string; name: string };
    people: number;
    evaluation: ClosedEvaluation | null;
    route: RouteProgress;
    rows: RouteRow[];
    /** The people to channel, only for whoever may see the Guía I. */
    urgencies: Urgency[];
    /** The end of the two-year cycle, "29 oct 2028". */
    until: string | null;
    inspectionHref: string;
    /** Null for whoever may not read it. */
    dossierHref: string | null;
}) {
    const tenantSlug = useTenantSlug();
    const stages = routeStages(route, rows);
    const current = stages.find((stage) => stage.state === 'current');
    const reach = inspectionReach(rows, current);
    setLayoutProps({
        breadcrumbs: [
            { title: 'NOM-035', href: index(tenantSlug) },
            { title: workCenter.name },
        ],
    });

    if (current === undefined) {
        return (
            <>
                <Head title={`NOM-035 · ${workCenter.name}`} />
                <div className="mx-auto flex w-full max-w-[1180px] flex-col gap-6 px-4 py-6 md:px-12 md:py-10">
                    <RouteDone
                        norm={normOf('nom035')}
                        until={until}
                        dossierHref={dossierHref ?? inspectionHref}
                        evaluationsHref={index(tenantSlug)}
                    />
                </div>
            </>
        );
    }

    return (
        <>
            <Head title={`NOM-035 · ${workCenter.name}`} />
            <div className="mx-auto flex w-full max-w-[1180px] flex-col gap-8 px-4 py-6 md:px-12 md:py-10">
                <header className="flex max-w-[760px] flex-col gap-2">
                    <h1 className="text-3xl leading-10 font-semibold tracking-[-0.015em] text-balance">
                        Tu ruta para cumplir con la NOM-035
                    </h1>
                    <p className="text-lg text-muted-foreground">
                        {routeIntro(workCenter.name, people, evaluation)}
                    </p>
                </header>
                <div className="grid gap-8 min-[1240px]:grid-cols-[minmax(0,1fr)_360px] min-[1240px]:grid-rows-[auto_1fr]">
                    {urgencies.length > 0 && (
                        <div className="flex flex-col gap-4 min-[1240px]:col-start-2 min-[1240px]:row-start-1">
                            <Nom035RouteBefore urgencies={urgencies} />
                        </div>
                    )}
                    <Nom035RouteStages
                        stages={stages}
                        inspectionHref={inspectionHref}
                        className="flex min-w-0 flex-col min-[1240px]:col-start-1 min-[1240px]:row-span-2 min-[1240px]:row-start-1"
                    />
                    <aside className="flex flex-col gap-4 min-[1240px]:col-start-2 min-[1240px]:row-start-2">
                        <RouteInspectionCard
                            {...reach}
                            stage={current.number}
                            href={inspectionHref}
                        >
                            <Nom035InspectionGaps rows={rows} />
                        </RouteInspectionCard>
                        <RouteHumiCard example="qué cuenta como violencia laboral o qué multa hay" />
                    </aside>
                </div>
            </div>
        </>
    );
}
