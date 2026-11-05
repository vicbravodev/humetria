import { Head, setLayoutProps } from '@inertiajs/react';
import ScenarioEditor from '@/components/compensation/scenario-editor';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import type { OtherScenario } from '@/lib/compensation/scenario-draft';
import { index } from '@/routes/tenant/compensation';
import type { AssumptionField, ChoiceOption, ScenarioDetail } from '@/types';

/** A scenario with its cost as it is moved (board V4-Comp-Escenario). */
export default function Scenario(props: {
    scenario: ScenarioDetail | null;
    assumptions: AssumptionField[];
    states: ChoiceOption[];
    defaults: { effective_on: string; horizon_years: number };
    horizons: number[];
    others: OtherScenario[];
}) {
    const tenantSlug = useTenantSlug();
    const title =
        props.scenario === null ? 'Nuevo escenario' : props.scenario.name;
    setLayoutProps({
        breadcrumbs: [
            { title: 'Compensación', href: index(tenantSlug) },
            { title },
        ],
    });

    return (
        <>
            <Head title={title} />
            <div className="mx-auto flex w-full max-w-[1240px] flex-col gap-8 px-4 py-6 md:px-10 md:pt-8 md:pb-16">
                {/* Another scenario is another form: its fields start from its own values. */}
                <ScenarioEditor key={props.scenario?.id ?? 'new'} {...props} />
            </div>
        </>
    );
}
