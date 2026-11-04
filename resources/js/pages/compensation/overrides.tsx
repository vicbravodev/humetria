import { Head } from '@inertiajs/react';
import NewOverrideButton from '@/components/compensation/new-override-button';
import OverridesEmpty from '@/components/compensation/overrides-empty';
import OverridesImport from '@/components/compensation/overrides-import';
import OverridesTable from '@/components/compensation/overrides-table';
import PageHeader from '@/components/humetria/page-header';
import Pagination from '@/components/humetria/pagination';
import SearchField from '@/components/humetria/search-field';
import { useOverrideFilters } from '@/hooks/use-override-filters';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { overridesHeadline } from '@/lib/compensation/salaries';
import type { OverrideRow, Paginated, SalaryFilters } from '@/types';

export default function Overrides({
    people,
    filters,
    max_rows,
}: {
    people: Paginated<OverrideRow>;
    filters: SalaryFilters;
    max_rows: number;
}) {
    const tenantSlug = useTenantSlug();
    const { text, type } = useOverrideFilters(tenantSlug, filters);

    return (
        <>
            <Head title="Sueldos individuales" />
            <div className="flex w-full flex-col gap-8 px-4 py-6 md:px-10 md:pt-8 md:pb-16">
                <PageHeader
                    title="Sueldos individuales"
                    description={`${overridesHeadline(people.total)} Son las excepciones: quien aparece aquí se calcula con su sueldo y no con el de su categoría.`}
                >
                    <NewOverrideButton />
                </PageHeader>
                <OverridesImport maxRows={max_rows} />
                <SearchField
                    label="Buscar por ID de la empresa o nota"
                    value={text}
                    onChange={type}
                    className="sm:max-w-[400px]"
                />
                {people.data.length === 0 ? (
                    <OverridesEmpty filtered={filters.q !== ''} />
                ) : (
                    <OverridesTable people={people.data} />
                )}
                <Pagination
                    page={people}
                    label="Páginas de sueldos individuales"
                />
            </div>
        </>
    );
}

Overrides.layout = {
    breadcrumbs: [
        { title: 'Herramientas' },
        { title: 'Compensación' },
        { title: 'Sueldos individuales' },
    ],
};
