import { Head, setLayoutProps } from '@inertiajs/react';
import BenefitCreateForm from '@/components/compensation/benefit-create-form';
import CompensationHeader from '@/components/compensation/compensation-header';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { index } from '@/routes/tenant/compensation';
import { index as benefits } from '@/routes/tenant/compensation/benefits';
import type { ChoiceOption } from '@/types';

/** «Nueva prestación» (board V4-Comp-Prestacion, decision A14). */
export default function BenefitCreate(props: {
    kinds: (ChoiceOption & { unit: string })[];
    frequencies: ChoiceOption[];
    groups: ChoiceOption[];
    sbc_integrations: ChoiceOption[];
    scenarios: number;
}) {
    const tenantSlug = useTenantSlug();
    setLayoutProps({
        breadcrumbs: [
            { title: 'Compensación', href: index(tenantSlug) },
            { title: 'Datos del contrato', href: benefits(tenantSlug) },
            { title: 'Nueva prestación' },
        ],
    });

    return (
        <>
            <Head title="Nueva prestación" />
            <div className="mx-auto flex w-full max-w-[1240px] flex-col gap-8 px-4 py-6 md:px-10 md:pt-8 md:pb-16">
                <CompensationHeader
                    eyebrow="Compensación · datos del contrato"
                    title="Nueva prestación"
                    lead="Contesta como viene en tu contrato. A la derecha ves cuánto cuesta hoy."
                />
                <BenefitCreateForm
                    kinds={props.kinds}
                    frequencies={props.frequencies}
                    sbcIntegrations={props.sbc_integrations}
                    scenarios={props.scenarios}
                />
            </div>
        </>
    );
}
