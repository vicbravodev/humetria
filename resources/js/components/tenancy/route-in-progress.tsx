import { Link } from '@inertiajs/react';
import NextStepCard from '@/components/humetria/next-step-card';
import DashboardUrgencies from '@/components/tenancy/dashboard-urgencies';
import RouteNotApplicable from '@/components/tenancy/route-not-applicable';
import RouteStages from '@/components/tenancy/route-stages';
import { Button } from '@/components/ui/button';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { STAGES } from '@/lib/compliance-routes';
import type { Norm } from '@/lib/compliance-routes';
import { stageHrefs } from '@/lib/route-links';
import type { RouteProgress, Urgency } from '@/types';

/**
 * A route on its way (board V3-Movil): what cannot wait, the next step with its button and the six stages; or, where it
 * does not start, its certificate or the question «¿Te aplica?».
 */
export default function RouteInProgress({
    norm,
    workCenter,
    route,
    urgencies,
}: {
    norm: Norm;
    workCenter: { id: string; name: string };
    route: RouteProgress;
    urgencies: Urgency[];
}) {
    const tenantSlug = useTenantSlug();
    const stages = STAGES[norm.key];
    const hrefs = stageHrefs(norm.key, tenantSlug, workCenter.id);
    const current = Math.max(route.done.indexOf(false), 0);
    const step = stages[current];
    const href = hrefs[current];

    return (
        <div className="flex max-w-[720px] flex-col gap-5">
            <div className="flex flex-col gap-1">
                <p className="text-meta text-muted-foreground">{`${norm.title} · ${workCenter.name}`}</p>
                <h1 className="text-3xl leading-10 font-semibold tracking-[-0.015em]">
                    Tu ruta de {norm.subtitle.toLowerCase()}
                </h1>
            </div>
            <DashboardUrgencies items={urgencies} title="Antes de seguir" />
            {!route.applies && (
                <RouteNotApplicable
                    route={route}
                    answerHref={
                        norm.answer?.(tenantSlug, workCenter.id) ?? null
                    }
                    certificateHref={
                        norm.certificate?.(tenantSlug, workCenter.id) ?? null
                    }
                />
            )}
            {route.applies && step !== undefined && href !== undefined && (
                <NextStepCard
                    title={step.title}
                    meta={`Etapa ${current + 1} de 6 · ${route.status}`}
                    action={
                        <Button asChild>
                            <Link href={href}>Continuar</Link>
                        </Button>
                    }
                >
                    {step.text}
                </NextStepCard>
            )}
            {route.applies && (
                <RouteStages
                    route={route}
                    titles={stages.map((stage) => stage.title)}
                    hrefs={hrefs}
                />
            )}
        </div>
    );
}
