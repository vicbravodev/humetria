import { Head } from '@inertiajs/react';
import ConsolePagination from '@/components/console-pagination';
import PageHeader from '@/components/page-header';
import CreateTenantDialog from '@/components/tenancy/create-tenant-dialog';
import TenantsEmpty from '@/components/tenancy/tenants-empty';
import TenantsTable from '@/components/tenancy/tenants-table';
import TenantsToolbar from '@/components/tenancy/tenants-toolbar';
import { companiesSummary } from '@/lib/tenant-status';
import type { Paginated, TenantFilters, TenantRow, TenantView } from '@/types';

export default function Tenants({
    tenants,
    activationMinDate,
    filters,
    counts,
    trialsEndingSoon,
}: {
    tenants: Paginated<TenantRow>;
    activationMinDate: string;
    filters: TenantFilters;
    counts: Record<TenantView, number>;
    trialsEndingSoon: number;
}) {
    return (
        <>
            <Head title="Empresas" />
            <div className="flex flex-col gap-8 px-4 py-6 md:px-10 md:py-8">
                <PageHeader
                    title="Empresas"
                    description={companiesSummary(counts.all, trialsEndingSoon)}
                >
                    <CreateTenantDialog />
                </PageHeader>
                {counts.all > 0 && (
                    <TenantsToolbar filters={filters} counts={counts} />
                )}
                {tenants.data.length === 0 ? (
                    <TenantsEmpty filtered={counts.all > 0} />
                ) : (
                    <TenantsTable
                        tenants={tenants.data}
                        activationMinDate={activationMinDate}
                    />
                )}
                <ConsolePagination page={tenants} label="Páginas de empresas" />
            </div>
        </>
    );
}
