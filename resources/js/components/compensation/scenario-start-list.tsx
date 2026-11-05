import ScenarioStartHeading from '@/components/compensation/scenario-start-heading';
import ScenarioStartRow from '@/components/compensation/scenario-start-row';
import ScenariosEmpty from '@/components/compensation/scenarios-empty';
import Pagination from '@/components/humetria/pagination';
import type { Paginated, ScenarioCounts, ScenarioRow } from '@/types';

/** «Tus escenarios» (board V4-Comp-Inicio), or «Tus propuestas» in a union: the active scenarios with their cost, paginated. */
export default function ScenarioStartList({
    scenarios,
    counts,
    today,
}: {
    scenarios: Paginated<ScenarioRow>;
    counts: ScenarioCounts;
    today: { people: number; cost_text: string } | null;
}) {
    const dearest = Math.max(
        0,
        ...scenarios.data.map((scenario) => scenario.first_year_cents),
    );

    return (
        <section aria-labelledby="start-scenarios" className="grid gap-4">
            <ScenarioStartHeading
                ids={scenarios.data.map((scenario) => scenario.id)}
                counts={counts}
                today={today}
            />
            {scenarios.data.length === 0 ? (
                <ScenariosEmpty archived={false} />
            ) : (
                <ul className="divide-y card">
                    {scenarios.data.map((scenario) => (
                        <ScenarioStartRow
                            key={scenario.id}
                            scenario={scenario}
                            dearest={dearest}
                        />
                    ))}
                </ul>
            )}
            <Pagination
                page={scenarios}
                label="Páginas de escenarios"
                pinned={false}
            />
        </section>
    );
}
