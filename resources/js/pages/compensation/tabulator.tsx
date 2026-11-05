import { Head, setLayoutProps } from '@inertiajs/react';
import ContractDataFrame from '@/components/compensation/contract-data-frame';
import NewSalaryButton from '@/components/compensation/new-salary-button';
import TabulatorEmpty from '@/components/compensation/tabulator-empty';
import TabulatorImport from '@/components/compensation/tabulator-import';
import TabulatorTable from '@/components/compensation/tabulator-table';
import Pagination from '@/components/humetria/pagination';
import SearchField from '@/components/humetria/search-field';
import { useTabulatorFilters } from '@/hooks/use-tabulator-filters';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import type { ContractStatus } from '@/lib/compensation/start';
import { index } from '@/routes/tenant/compensation';
import type { Paginated, SalaryFilters, TabulatorRow } from '@/types';

/** «Tabulador» among the data of the contract (board V4-Comp-Datos): a category without salary is captured in its row, and a CSV is dropped below. */
export default function Tabulator({
    grades,
    filters,
    max_rows,
    status,
    today,
}: {
    grades: Paginated<TabulatorRow>;
    filters: SalaryFilters;
    max_rows: number;
    status: ContractStatus;
    today: string;
}) {
    const tenantSlug = useTenantSlug();
    const { text, type } = useTabulatorFilters(tenantSlug, filters);
    setLayoutProps({
        breadcrumbs: [
            { title: 'Compensación', href: index(tenantSlug) },
            { title: 'Datos del contrato' },
        ],
    });

    return (
        <>
            <Head title="Tabulador" />
            <div className="mx-auto flex w-full max-w-[1240px] flex-col gap-8 px-4 py-6 md:px-10 md:pt-8 md:pb-16">
                <ContractDataFrame
                    status={status}
                    current="tabulator"
                    title="Tabulador"
                    lead="El salario diario de cada categoría y nivel. Cada cambio guarda su fecha; los anteriores quedan en el historial."
                    action={<NewSalaryButton />}
                >
                    {(grades.total > 15 || filters.q !== '') && (
                        <SearchField
                            label="Buscar una categoría o un nivel"
                            value={text}
                            onChange={type}
                            className="sm:max-w-[400px]"
                        />
                    )}
                    {grades.data.length === 0 ? (
                        <TabulatorEmpty filtered={filters.q !== ''} />
                    ) : (
                        <TabulatorTable grades={grades.data} today={today} />
                    )}
                    <Pagination
                        page={grades}
                        label="Páginas del tabulador"
                        pinned={false}
                    />
                    <TabulatorImport maxRows={max_rows} />
                </ContractDataFrame>
            </div>
        </>
    );
}
