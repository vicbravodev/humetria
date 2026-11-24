import { Head, Link } from '@inertiajs/react';
import { Layers } from 'lucide-react';
import EmptyState from '@/components/humetria/empty-state';
import PageHeader from '@/components/humetria/page-header';
import SupportLink from '@/components/humetria/support-link';
import PlanTools from '@/components/tenancy/plan-tools';
import PlanUsage from '@/components/tenancy/plan-usage';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { edit } from '@/routes/tenant/channels';
import type { CompanyPlan, PlanTool, PlanUse } from '@/types';

/** «Plan y uso» (the owner's): which plan the company has, what it uses of its limits and which tools it has. */
export default function Plan({
    plan,
    union,
    usage,
    tools,
}: {
    plan: CompanyPlan;
    union: boolean;
    usage: { centers: PlanUse | null; people: PlanUse };
    tools: PlanTool[];
}) {
    const tenantSlug = useTenantSlug();

    return (
        <>
            <Head title="Plan y uso" />
            <div className="flex flex-col gap-8 px-4 py-6 md:px-10 md:py-8">
                <PageHeader
                    title="Plan y uso"
                    description={
                        plan === null
                            ? 'Lo que usa tu empresa y las herramientas que tiene.'
                            : `Plan ${plan.name} · desde el ${plan.since}, se renueva el ${plan.until}.`
                    }
                />
                {plan === null && (
                    <EmptyState
                        icon={Layers}
                        title="Tu empresa aún no tiene un plan asignado"
                    >
                        Mientras tanto usas todas las herramientas sin tope.{' '}
                        <SupportLink subject="Quiero contratar un plan">
                            Escríbenos para contratar uno
                        </SupportLink>
                        .
                    </EmptyState>
                )}
                <PlanUsage centers={usage.centers} people={usage.people} />
                {!union && <PlanTools tools={tools} />}
                <p className="text-sm text-muted-foreground">
                    Por dónde les escribimos a tus personas:{' '}
                    <Link
                        href={edit(tenantSlug)}
                        className="font-medium text-primary underline-offset-4 hover:underline"
                    >
                        Canales de comunicación
                    </Link>
                    .
                </p>
                {plan !== null && (
                    <p className="text-sm text-muted-foreground">
                        Para ampliar tu plan o cambiar de herramientas,{' '}
                        <SupportLink subject="Quiero ampliar mi plan">
                            escríbenos
                        </SupportLink>
                        .
                    </p>
                )}
            </div>
        </>
    );
}
