import { Head, Link, setLayoutProps } from '@inertiajs/react';
import ProgressBar from '@/components/humetria/progress-bar';
import RouteHumiCard from '@/components/humetria/route-humi-card';
import RouteInspectionCard from '@/components/humetria/route-inspection-card';
import RouteStage from '@/components/humetria/route-stage';
import RouteBefore from '@/components/seating/route-before';
import RouteInputsCard from '@/components/seating/route-inputs-card';
import RouteTasks from '@/components/seating/route-tasks';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { inspectionReach, stageDone } from '@/lib/route-stages';
import { routeIntro, routeStages } from '@/lib/seating/route';
import type { LateReports, PeopleInputs } from '@/lib/seating/route';
import { index } from '@/routes/tenant/seating';
import type { RouteProgress, RouteRow } from '@/types';

/** Board V2-Ruta-Silla: the Ley Silla route of a center, its six stages in order, and what cannot wait beside it. */
export default function SeatingRoute({
    workCenter,
    people,
    positions,
    route,
    rows,
    reports,
    inputs,
    inspectionHref,
}: {
    workCenter: { id: string; name: string };
    people: number;
    positions: number;
    route: RouteProgress;
    rows: RouteRow[];
    /** Null without late reports, or for whoever does not resolve them. */
    reports: LateReports | null;
    /** Null for whoever does not assess (the viewer). */
    inputs: PeopleInputs | null;
    inspectionHref: string;
}) {
    const tenantSlug = useTenantSlug();
    const stages = routeStages(route, rows);
    const current = stages.find((stage) => stage.state === 'current');
    const reach = inspectionReach(rows, current);
    setLayoutProps({
        breadcrumbs: [
            { title: 'Ley Silla', href: index(tenantSlug) },
            { title: workCenter.name },
        ],
    });

    return (
        <>
            <Head title={`Ley Silla · ${workCenter.name}`} />
            <div className="mx-auto flex w-full max-w-[1180px] flex-col gap-8 px-4 py-6 md:px-12 md:py-10">
                <header className="flex max-w-[760px] flex-col gap-2">
                    <h1 className="text-3xl leading-10 font-semibold tracking-[-0.015em] text-balance">
                        Tu ruta para cumplir con la Ley Silla
                    </h1>
                    <p className="text-lg text-muted-foreground">
                        {routeIntro(
                            workCenter.name,
                            { people, positions },
                            route,
                        )}
                    </p>
                </header>
                <div className="grid gap-8 min-[1240px]:grid-cols-[minmax(0,1fr)_360px] min-[1240px]:grid-rows-[auto_1fr]">
                    {reports !== null && (
                        <div className="flex flex-col gap-4 min-[1240px]:col-start-2 min-[1240px]:row-start-1">
                            <RouteBefore reports={reports} />
                        </div>
                    )}
                    <ol
                        aria-label="Etapas de la Ley Silla"
                        className="flex min-w-0 flex-col min-[1240px]:col-start-1 min-[1240px]:row-span-2 min-[1240px]:row-start-1"
                    >
                        {stages.map((stage) => (
                            <RouteStage
                                key={stage.number}
                                stage={stage}
                                done={stageDone(stage)}
                            >
                                {stage.state === 'current' && (
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
                        >
                            <ProgressBar
                                value={reach.ready}
                                max={reach.applicable}
                                label={`${reach.ready} de ${reach.applicable} cosas que podrías mostrar`}
                            />
                        </RouteInspectionCard>
                        {inputs !== null && inputs.people > 0 && (
                            <RouteInputsCard inputs={inputs} />
                        )}
                        <RouteHumiCard example="dónde va el asiento según la Figura 1 o qué multa hay" />
                    </aside>
                </div>
            </div>
        </>
    );
}
