import { Head } from '@inertiajs/react';
import ScenarioForm from '@/components/compensation/scenario-form';
import PageHeader from '@/components/humetria/page-header';
import type { AssumptionField, ChoiceOption, ScenarioDetail } from '@/types';

export default function Scenario({
    scenario,
    assumptions,
    states,
    defaults,
    horizons,
}: {
    scenario: ScenarioDetail | null;
    assumptions: AssumptionField[];
    states: ChoiceOption[];
    defaults: { effective_on: string; horizon_years: number };
    horizons: number[];
}) {
    const title = scenario === null ? 'Nuevo escenario' : scenario.name;

    return (
        <>
            <Head title={title} />
            <div className="flex w-full flex-col gap-8 px-4 py-6 md:px-10 md:pt-8 md:pb-16">
                <PageHeader
                    title={title}
                    description="Define la propuesta: cuánto sube el salario, qué prestaciones cambian y qué supuestos se usan para los años siguientes."
                />
                <ScenarioForm
                    // Another scenario is another form: its fields start from its own values.
                    key={scenario?.id ?? 'new'}
                    scenario={scenario}
                    assumptions={assumptions}
                    states={states}
                    defaults={defaults}
                    horizons={horizons}
                />
            </div>
        </>
    );
}

Scenario.layout = {
    breadcrumbs: [
        { title: 'Herramientas' },
        { title: 'Compensación' },
        { title: 'Escenarios' },
    ],
};
