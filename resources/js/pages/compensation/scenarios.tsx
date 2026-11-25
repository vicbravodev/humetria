import { Head, setLayoutProps } from '@inertiajs/react';
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
    ScenarioSource,
} from '@/types';

export default function Scenarios({
    source,
    scenarios,
    counts,
    filters,
}: {
    source: ScenarioSource;
    scenarios: Paginated<ScenarioRow>;
    counts: ScenarioCounts;
    filters: ScenarioFilters;
}) {
    const { chosen, full, ready, toggle } = useScenarioSelection();
    const quick = source === 'average';
    const title = quick ? 'Cotizador rápido' : 'Escenarios';
    setLayoutProps({
        breadcrumbs: [
            { title: 'Ordena tu operación' },
            { title: 'Compensación' },
            { title },
        ],
    });

    return (
        <>
            <Head title={`${title} · Compensación`} />
            <div className="flex w-full flex-col gap-8 px-4 py-6 md:px-10 md:pt-8 md:pb-16">
                <PageHeader
                    title={title}
                    description={`${scenariosHeadline(counts)} ${
                        quick
                            ? 'Cada uno parte de un promedio, cuántas personas son y cuánto ganan, y te da lo que cuesta el aumento, su reporte y la comparación, sin cargar plantilla ni tabulador.'
                            : 'Cada uno es una propuesta de revisión del contrato: lo que sube el salario, lo que cambia en prestaciones y lo que cuesta.'
                    }`}
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
