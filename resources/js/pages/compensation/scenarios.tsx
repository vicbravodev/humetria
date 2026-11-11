import { Head } from '@inertiajs/react';
import CompareScenariosButton from '@/components/compensation/compare-scenarios-button';
import NewScenarioButton from '@/components/compensation/new-scenario-button';
import ScenariosEmpty from '@/components/compensation/scenarios-empty';
import ScenariosTable from '@/components/compensation/scenarios-table';
import ScenariosToolbar from '@/components/compensation/scenarios-toolbar';
import PageHeader from '@/components/humetria/page-header';
import Pagination from '@/components/humetria/pagination';
import { useScenarioSelection } from '@/hooks/use-scenario-selection';
import { scenariosHeadline } from '@/lib/compensation/scenarios';
import type {
    Paginated,
    ScenarioCounts,
    ScenarioFilters,
    ScenarioRow,
} from '@/types';

export default function Scenarios({
    scenarios,
    counts,
    filters,
}: {
    scenarios: Paginated<ScenarioRow>;
    counts: ScenarioCounts;
    filters: ScenarioFilters;
}) {
    const { chosen, full, ready, toggle } = useScenarioSelection();

    return (
        <>
            <Head title="Escenarios" />
            <div className="flex w-full flex-col gap-8 px-4 py-6 md:px-10 md:pt-8 md:pb-16">
                <PageHeader
                    title="Escenarios"
                    description={`${scenariosHeadline(counts)} Cada uno es una propuesta de revisión del contrato: lo que sube el salario, lo que cambia en prestaciones y lo que cuesta.`}
                >
                    <CompareScenariosButton chosen={chosen} ready={ready} />
                    <NewScenarioButton />
                </PageHeader>
                <ScenariosToolbar filters={filters} counts={counts} />
                {scenarios.data.length === 0 ? (
                    <ScenariosEmpty archived={filters.status === 'archived'} />
                ) : (
                    <ScenariosTable
                        scenarios={scenarios.data}
                        chosen={chosen}
                        full={full}
                        onChoose={toggle}
                    />
                )}
                <Pagination page={scenarios} label="Páginas de escenarios" />
            </div>
        </>
    );
}

Scenarios.layout = {
    breadcrumbs: [
        { title: 'Ordena tu operación' },
        { title: 'Compensación' },
        { title: 'Escenarios' },
    ],
};
