import { Head, setLayoutProps } from '@inertiajs/react';
import { ClipboardList } from 'lucide-react';
import ResultsTable from '@/components/disclosure/results-table';
import EmptyState from '@/components/humetria/empty-state';
import PageHeader from '@/components/humetria/page-header';
import Pagination from '@/components/humetria/pagination';
import type { ResultsProps } from '@/types';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { disclosureCrumbs } from '@/lib/nom035/crumbs';

export default function Results({ evaluations, pageActive }: ResultsProps) {
    const tenantSlug = useTenantSlug();
    setLayoutProps({
        breadcrumbs: disclosureCrumbs(tenantSlug, {
            title: 'Resultados para las personas',
        }),
    });

    return (
        <>
            <Head title="Resultados para las personas" />
            <div className="flex w-full flex-col gap-8 px-4 py-6 md:px-10 md:pt-8 md:pb-16">
                <PageHeader
                    caption="NOM-035 · Difusión"
                    title="Resultados para las personas"
                    description="Las personas tienen derecho a consultar el resultado de la evaluación (numeral 7.8). Tú decides cuándo se publica cada una: solo se muestran cifras del centro completo y de grupos grandes, nunca de áreas pequeñas."
                />
                {evaluations.total === 0 ? (
                    <EmptyState
                        icon={ClipboardList}
                        title="Aún no hay evaluaciones cerradas"
                    >
                        Cuando cierres una evaluación podrás publicar su
                        resultado aquí para las personas de ese centro.
                    </EmptyState>
                ) : (
                    <section
                        aria-label="Evaluaciones cerradas"
                        className="flex flex-col gap-3.5"
                    >
                        <ResultsTable
                            rows={evaluations.data}
                            pageActive={pageActive}
                        />
                        <Pagination
                            page={evaluations}
                            label="Páginas de evaluaciones"
                            previous="Ver más recientes"
                            next="Ver más antiguas"
                        />
                    </section>
                )}
            </div>
        </>
    );
}
