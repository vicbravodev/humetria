import { Head, Link, setLayoutProps } from '@inertiajs/react';
import RouteBefore from '@/components/telework/route-before';
import RouteHumiCard from '@/components/telework/route-humi-card';
import RouteInspectionCard from '@/components/telework/route-inspection-card';
import RouteStage from '@/components/telework/route-stage';
import RouteTasks from '@/components/telework/route-tasks';
import { Badge } from '@/components/ui/badge';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { inspectionReach, routeIntro, routeStages } from '@/lib/telework/route';
import { index } from '@/routes/tenant/telework';
import type { RouteAccidents, RouteProgress, RouteRow } from '@/types';

/** Board V2-Ruta-N037: the NOM-037 route of a center, its six stages in order, and what cannot wait beside it. */
export default function TeleworkRoute({
    workCenter,
    teleworkers,
    route,
    rows,
    accidents,
    inspectionHref,
}: {
    workCenter: { id: string; name: string };
    teleworkers: number;
    route: RouteProgress;
    rows: RouteRow[];
    accidents: RouteAccidents[];
    inspectionHref: string;
}) {
    const tenantSlug = useTenantSlug();
    const stages = routeStages(route, rows);
    const current = stages.find((stage) => stage.state === 'current');
    const reach = inspectionReach(rows, current);
    const waiting = accidents.reduce((sum, each) => sum + each.count, 0);
    setLayoutProps({
        breadcrumbs: [
            { title: 'NOM-037', href: index(tenantSlug) },
            { title: workCenter.name },
        ],
    });

    return (
        <>
            <Head title={`NOM-037 · ${workCenter.name}`} />
            <div className="mx-auto flex w-full max-w-[1180px] flex-col gap-8 px-4 py-6 md:px-12 md:py-10">
                <header className="flex max-w-[760px] flex-col gap-2">
                    <h1 className="text-3xl leading-10 font-semibold tracking-[-0.015em] text-balance">
                        Tu ruta para cumplir con el teletrabajo
                    </h1>
                    <p className="text-lg text-muted-foreground">
                        {routeIntro(workCenter.name, teleworkers, route)}
                    </p>
                </header>
                <div className="grid gap-8 min-[1240px]:grid-cols-[minmax(0,1fr)_360px] min-[1240px]:grid-rows-[auto_1fr]">
                    {accidents.length > 0 && (
                        <div className="flex flex-col gap-4 min-[1240px]:col-start-2 min-[1240px]:row-start-1">
                            <RouteBefore accidents={accidents} />
                        </div>
                    )}
                    <ol
                        aria-label="Etapas de la NOM-037"
                        className="flex min-w-0 flex-col min-[1240px]:col-start-1 min-[1240px]:row-span-2 min-[1240px]:row-start-1"
                    >
                        {stages.map((stage) => (
                            <RouteStage
                                key={stage.number}
                                stage={stage}
                                badge={
                                    stage.number === 5 && waiting > 0 ? (
                                        <Badge variant="destructive">
                                            {waiting === 1
                                                ? '1 accidente sin revisar'
                                                : `${waiting} accidentes sin revisar`}
                                        </Badge>
                                    ) : undefined
                                }
                            >
                                {stage.state === 'current' &&
                                    stage.number < 6 && (
                                        <RouteTasks stage={stage} />
                                    )}
                                {stage.number === 6 && (
                                    <Link
                                        href={inspectionHref}
                                        className="touch-hit self-start text-sm font-medium text-primary hover:underline"
                                    >
                                        Ver lo que te pediría una inspección
                                    </Link>
                                )}
                            </RouteStage>
                        ))}
                    </ol>
                    <aside className="flex flex-col gap-4 min-[1240px]:col-start-2 min-[1240px]:row-start-2">
                        <RouteInspectionCard
                            {...reach}
                            stage={current?.number ?? null}
                            href={inspectionHref}
                        />
                        <RouteHumiCard />
                    </aside>
                </div>
            </div>
        </>
    );
}
