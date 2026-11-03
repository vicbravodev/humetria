import { Head, setLayoutProps } from '@inertiajs/react';
import { Factory } from 'lucide-react';
import PageHeader from '@/components/humetria/page-header';
import SearchField from '@/components/humetria/search-field';
import AssessmentsTable from '@/components/seating/assessments-table';
import RequestLinkButton from '@/components/seating/request-link-button';
import { useQueryFilters } from '@/hooks/use-query-filters';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { center, index } from '@/routes/tenant/seating';
import { batch } from '@/routes/tenant/seating/acknowledgments';
import { index as assessments } from '@/routes/tenant/seating/assessments';
import type { AssessmentPerson, Paginated } from '@/types';

export default function SeatingAssessments({
    workCenter,
    people,
    search,
    canAssess,
    canManage,
}: {
    workCenter: { id: string; name: string };
    people: Paginated<AssessmentPerson>;
    search: string;
    canAssess: boolean;
    canManage: boolean;
}) {
    const tenantSlug = useTenantSlug();
    setLayoutProps({
        breadcrumbs: [
            { title: 'Ley Silla', href: index(tenantSlug) },
            {
                title: workCenter.name,
                href: center([tenantSlug, workCenter.id]),
            },
            { title: 'Evaluaciones' },
        ],
    });
    const { text, type } = useQueryFilters(
        assessments.url([tenantSlug, workCenter.id]),
        { q: search },
        (filters) => ({ q: filters.q }),
    );

    return (
        <>
            <Head title={`Evaluaciones · ${workCenter.name}`} />
            <div className="flex w-full flex-col gap-6 px-4 py-6 md:px-10 md:pt-8 md:pb-16">
                <PageHeader
                    title="Evaluaciones"
                    description="El nivel de riesgo de cada persona que trabaja de pie. Lo firma una persona de la Comisión del centro."
                    facts={[{ icon: Factory, text: workCenter.name }]}
                >
                    {canManage && (
                        <RequestLinkButton
                            form={batch.form([tenantSlug, workCenter.id])}
                            label="Pedir confirmación a quienes faltan"
                        />
                    )}
                </PageHeader>
                <SearchField
                    label="Buscar por nombre"
                    value={text}
                    onChange={type}
                    className="max-w-md"
                />
                <AssessmentsTable
                    workCenterId={workCenter.id}
                    people={people}
                    canAssess={canAssess}
                />
            </div>
        </>
    );
}
