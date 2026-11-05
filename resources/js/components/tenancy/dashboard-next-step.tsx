import { Link } from '@inertiajs/react';
import NextStepCard from '@/components/humetria/next-step-card';
import { Button } from '@/components/ui/button';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { NORMS } from '@/lib/compliance-routes';
import { nextStep } from '@/lib/dashboard';
import { countOf } from '@/lib/plural';
import type { CenterRoutes } from '@/types';

/** The step the panel recommends: the earliest stage left of any route, and the way to it. */
export default function DashboardNextStep({
    centers,
}: {
    centers: CenterRoutes[];
}) {
    const tenantSlug = useTenantSlug();
    const step = nextStep(centers);
    const norm = NORMS.find((each) => each.key === step?.norm);

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
