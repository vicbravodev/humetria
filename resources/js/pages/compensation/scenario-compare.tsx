import { Head } from '@inertiajs/react';
import ScenarioCompareTable from '@/components/compensation/scenario-compare-table';
import PageHeader from '@/components/humetria/page-header';
import type { CompareRow, CompareScenario } from '@/types';

export default function ScenarioCompare({
    scenarios,
    rows,
}: {
    scenarios: CompareScenario[];
    rows: CompareRow[];
}) {
    return (
        <>
            <Head title="Comparar escenarios" />
            <div className="flex w-full flex-col gap-8 px-4 py-6 md:px-10 md:pt-8 md:pb-16">
                <PageHeader
                    title="Comparar escenarios"
                    description="Lo que cambia cada propuesta en el primer año, concepto por concepto, con los meses de retroactivo incluidos. Un concepto que un escenario no toca le cuesta cero."
                />
                <ScenarioCompareTable scenarios={scenarios} rows={rows} />
            </div>
        </>
    );
}

ScenarioCompare.layout = {
    breadcrumbs: [
        { title: 'Herramientas' },
        { title: 'Compensación' },
        { title: 'Escenarios' },
        { title: 'Comparar' },
    ],
};
