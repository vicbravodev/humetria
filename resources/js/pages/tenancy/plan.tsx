import { Head } from '@inertiajs/react';
import { Layers } from 'lucide-react';
import EmptyState from '@/components/humetria/empty-state';
import PageHeader from '@/components/humetria/page-header';
import SupportLink from '@/components/humetria/support-link';
import PlanTools from '@/components/tenancy/plan-tools';
import PlanUsage from '@/components/tenancy/plan-usage';
import type { CompanyPlan, PlanTool, PlanUse } from '@/types';

/** «Plan y uso» (the owner's): which plan the company has, what it uses of its limits and which tools it has. */
export default function Plan({
    plan,
    usage,
    tools,
}: {
    plan: CompanyPlan;
    usage: { centers: PlanUse; people: PlanUse };
    tools: PlanTool[];
}) {
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
                <PlanTools tools={tools} />
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
