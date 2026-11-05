import { Head } from '@inertiajs/react';
import NewSalaryButton from '@/components/compensation/new-salary-button';
import TabulatorEmpty from '@/components/compensation/tabulator-empty';
import TabulatorImport from '@/components/compensation/tabulator-import';
import TabulatorTable from '@/components/compensation/tabulator-table';
import PageHeader from '@/components/humetria/page-header';
import Pagination from '@/components/humetria/pagination';
import SearchField from '@/components/humetria/search-field';
import { useTabulatorFilters } from '@/hooks/use-tabulator-filters';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { tabulatorHeadline } from '@/lib/compensation/salaries';
import type { Paginated, SalaryFilters, TabulatorRow } from '@/types';

export default function Tabulator({
    grades,
    filters,
    max_rows,
}: {
    grades: Paginated<TabulatorRow>;
    filters: SalaryFilters;
    max_rows: number;
}) {
    const tenantSlug = useTenantSlug();
    const { text, type } = useTabulatorFilters(tenantSlug, filters);

    return (
        <>
            <Head title="Tabulador" />
            <div className="flex w-full flex-col gap-8 px-4 py-6 md:px-10 md:pt-8 md:pb-16">
                <PageHeader
                    title="Tabulador"
                    description={`${tabulatorHeadline(grades.total)} Aquí defines el salario diario de cada categoría y nivel: el punto de partida de cada escenario.`}
                >
                    <NewSalaryButton />
                </PageHeader>
                <TabulatorImport maxRows={max_rows} />
                <SearchField
                    label="Buscar una categoría o un nivel"
                    value={text}
                    onChange={type}
                    className="sm:max-w-[400px]"
                />
                {grades.data.length === 0 ? (
                    <TabulatorEmpty filtered={filters.q !== ''} />
                ) : (
                    <TabulatorTable grades={grades.data} />
                )}
                <Pagination page={grades} label="Páginas del tabulador" />
            </div>
        </>
    );
}

Tabulator.layout = {
    breadcrumbs: [
        { title: 'Herramientas' },
        { title: 'Compensación' },
        { title: 'Tabulador' },
    ],
};
