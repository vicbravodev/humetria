import { Head, setLayoutProps } from '@inertiajs/react';
import { ClipboardList } from 'lucide-react';
import EmptyState from '@/components/humetria/empty-state';
import PageHeader from '@/components/humetria/page-header';
import Pagination from '@/components/humetria/pagination';
import ProgramsSummary from '@/components/interventions/programs-summary';
import ProgramsTable from '@/components/interventions/programs-table';
import type {
    Paginated,
    ProgramListRow,
    ProgramsSummary as Summary,
} from '@/types';

export default function ProgramsIndex({
    summary,
    evaluations,
}: {
    summary: Summary;
    evaluations: Paginated<ProgramListRow>;
}) {
    setLayoutProps({
        breadcrumbs: [
            { title: 'NOM-035' },
            { title: 'Programas de intervención' },
        ],
    });

    return (
        <>
            <Head title="Programas de intervención" />
            <div className="flex w-full flex-col gap-8 px-4 py-6 md:px-10 md:pt-8 md:pb-16">
                <PageHeader
                    title="Programas de intervención"
                    description="Lo que haces con el resultado de cada evaluación: acciones con fecha y responsable, el control de sus avances y la evaluación posterior (numerales 8.3 a 8.5)."
                />
                <ProgramsSummary summary={summary} />
                {evaluations.total === 0 ? (
                    <EmptyState
                        icon={ClipboardList}
                        title="Aún no hay evaluaciones cerradas"
                    >
                        Cuando cierres una evaluación con riesgo medio o más, su
                        Programa aparecerá aquí listo para completarlo.
                    </EmptyState>
                ) : (
                    <section
                        aria-label="Evaluaciones"
                        className="flex flex-col gap-3.5"
                    >
                        <h2 className="text-lg leading-[26px] font-semibold">
                            Evaluaciones cerradas
                        </h2>
                        <ProgramsTable rows={evaluations.data} />
                        <Pagination
                            previous="Ver más recientes"
                            next="Ver más antiguas"
                            page={evaluations}
                            label="Páginas de evaluaciones"
                        />
                    </section>
                )}
            </div>
        </>
    );
}
