import { Head, setLayoutProps } from '@inertiajs/react';
import ContractDataFrame from '@/components/compensation/contract-data-frame';
import NewOverrideButton from '@/components/compensation/new-override-button';
import OverridesEmpty from '@/components/compensation/overrides-empty';
import OverridesImport from '@/components/compensation/overrides-import';
import OverridesTable from '@/components/compensation/overrides-table';
import Pagination from '@/components/humetria/pagination';
import SearchField from '@/components/humetria/search-field';
import { useOverrideFilters } from '@/hooks/use-override-filters';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { overridesHeadline } from '@/lib/compensation/salaries';
import type { ContractStatus } from '@/lib/compensation/start';
import { index } from '@/routes/tenant/compensation';
import type { OverrideRow, Paginated, SalaryFilters } from '@/types';

export default function Overrides({
    people,
    filters,
    max_rows,
    status,
}: {
    people: Paginated<OverrideRow>;
    filters: SalaryFilters;
    max_rows: number;
    status: ContractStatus;
}) {
    const tenantSlug = useTenantSlug();
    const { text, type } = useOverrideFilters(tenantSlug, filters);
    setLayoutProps({
        breadcrumbs: [
            { title: 'Compensación', href: index(tenantSlug) },
            { title: 'Datos del contrato' },
        ],
    });

    return (
        <>
            <Head title="Sueldos individuales" />
            <div className="mx-auto flex w-full max-w-[1240px] flex-col gap-8 px-4 py-6 md:px-10 md:pt-8 md:pb-16">
                <ContractDataFrame
                    status={status}
                    current="overrides"
                    title="Sueldos individuales"
                    lead={`${overridesHeadline(people.total)} Son las excepciones: quien aparece aquí se calcula con su sueldo y no con el de su categoría.`}
                    action={<NewOverrideButton />}
                >
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
                    <OverridesImport maxRows={max_rows} />
                </ContractDataFrame>
            </div>
        </>
    );
}
