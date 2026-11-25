import { Head, setLayoutProps } from '@inertiajs/react';
import ScenarioEditor from '@/components/compensation/scenario-editor';
import { useScenarioCrumbs } from '@/hooks/use-scenario-routes';
import type { ScenarioDefaults } from '@/lib/compensation/raise-draft';
import type { OtherScenario } from '@/lib/compensation/scenario-draft';
import type {
    AssumptionField,
    ChoiceOption,
    ScenarioDetail,
    ScenarioPayroll,
    ScenarioSource,
} from '@/types';

/** A scenario with its cost as it is moved (board V4-Comp-Escenario). */
export default function Scenario(props: {
    source: ScenarioSource;
    scenario: ScenarioDetail | null;
    assumptions: AssumptionField[];
    states: ChoiceOption[];
    defaults: ScenarioDefaults & ScenarioPayroll;
    horizons: number[];
    others: OtherScenario[];
    vacation_notice: string | null;
}) {
    const title =
        props.scenario === null ? 'Nuevo escenario' : props.scenario.name;
    const breadcrumbs = useScenarioCrumbs({ title });
    setLayoutProps({ breadcrumbs });

    return (
        <>
            <Head title={title} />
            <div className="flex w-full flex-col gap-8 px-4 py-6 md:px-10 md:pt-8 md:pb-16">
                {/* Another scenario is another form: its fields start from its own values. */}
                <ScenarioEditor key={props.scenario?.id ?? 'new'} {...props} />
            </div>
        </>
    );
}
