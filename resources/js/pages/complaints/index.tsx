import { Head } from '@inertiajs/react';
import CasesTable from '@/components/complaints/cases-table';
import CasesToolbar from '@/components/complaints/cases-toolbar';
import ConfidentialBanner from '@/components/complaints/confidential-banner';
import PageHeader from '@/components/humetria/page-header';
import type { Option } from '@/lib/option-search';
import type { CaseCounts, CaseFilters, CaseRow, Paginated } from '@/types';

export default function Cases({
    cases,
    status,
    counts,
    filters,
    types,
}: {
    cases: Paginated<CaseRow>;
    status: CaseFilters['status'];
    counts: CaseCounts;
    filters: Omit<CaseFilters, 'status'>;
    types: Option[];
}) {
    return (
        <>
            <Head title="Quejas y denuncias" />
            <div className="flex w-full max-w-[1180px] flex-col gap-6 px-4 py-6 md:px-10 md:pt-8 md:pb-16">
                <ConfidentialBanner />
                <PageHeader
                    caption="NOM-035 · Numeral 8.1 b"
                    title="Quejas y denuncias"
                    description="Los casos que te asignaron. Un caso que no tengas asignado no existe para ti, aunque administres el canal."
                />
                <CasesToolbar
                    filters={{ ...filters, status }}
                    counts={counts}
                    types={types}
                />
                <CasesTable cases={cases} closed={status === 'closed'} />
            </div>
        </>
    );
}

Cases.layout = {
    breadcrumbs: [{ title: 'NOM-035' }, { title: 'Quejas y denuncias' }],
};
