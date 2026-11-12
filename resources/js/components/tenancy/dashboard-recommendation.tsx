import { Link, usePage } from '@inertiajs/react';
import NextStepCard from '@/components/humetria/next-step-card';
import { Button } from '@/components/ui/button';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { NORMS } from '@/lib/compliance-routes';
import { nextStep, unansweredCenters } from '@/lib/dashboard';
import { onboarding } from '@/routes/tenant/work-centers';
import { countOf } from '@/lib/plural';
import type { CenterRoutes } from '@/types';

/**
 * The recommended step: first, for whoever sets the standards up, the two questions of the centers that have not
 * answered «¿Te aplica?» (board V3-Onboarding); then the earliest stage left of any route, and the way to it.
 */
export default function DashboardRecommendation({
    centers,
    setsUp,
}: {
    centers: CenterRoutes[];
    setsUp: boolean;
}) {
    const tenantSlug = useTenantSlug();
    const modules = usePage().props.tenant?.modules;
    const step = nextStep(centers, modules);
    const norm = NORMS.find((each) => each.key === step?.norm);
    const unanswered = unansweredCenters(centers, modules);

    if (setsUp && unanswered > 0) {
        return (
            <NextStepCard
                title="Dos preguntas por centro y armamos todo"
                meta={`${countOf(unanswered, 'centro', 'centros')} sin contestar · ≈ 1 min cada uno`}
                action={
                    <Button asChild>
                        <Link href={onboarding(tenantSlug)}>Empezar</Link>
                    </Button>
                }
            >
                Con esto sabemos qué normas te tocan en cada centro y creamos
                tus rutas.
            </NextStepCard>
        );
    }

    if (step === null || norm === undefined) {
        return null;
    }

    return (
        <NextStepCard
            title={step.title}
            meta={`${norm.title} · etapa ${step.stage} de 6 · ${countOf(step.centers, 'centro', 'centros')}`}
            action={
                <Button asChild>
                    <Link href={norm.href(tenantSlug)}>Continuar</Link>
                </Button>
            }
        >
            {step.text}
        </NextStepCard>
    );
}
