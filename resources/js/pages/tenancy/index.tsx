import { Head, usePage } from '@inertiajs/react';
import Pagination from '@/components/humetria/pagination';
import PageHeader from '@/components/humetria/page-header';
import CreateTenantDialog from '@/components/tenancy/create-tenant-dialog';
import TenantsEmpty from '@/components/tenancy/tenants-empty';
import TenantsTable from '@/components/tenancy/tenants-table';
import TenantsToolbar from '@/components/tenancy/tenants-toolbar';
import { companiesSummary, tenantsFiltered } from '@/lib/tenant-status';
import type {
    Paginated,
    TenantFilters,
    TenantPrefill,
    TenantRow,
    TenantView,
} from '@/types';

export default function Tenants({
    tenants,
    activationMinDate,
    filters,
    counts,
    trialsEndingSoon,
    prefill,
}: {
    tenants: Paginated<TenantRow>;
    activationMinDate: string;
    filters: TenantFilters;
    counts: Record<TenantView, number>;
    trialsEndingSoon: number;
    prefill: TenantPrefill | null;
}) {
    const { auth } = usePage().props;
    const filtered = tenantsFiltered(counts, filters);

    return (
        <>
            <Head title="Empresas" />
            <div className="flex flex-col gap-8 px-4 py-6 md:px-10 md:py-8">
                <PageHeader
                    title="Empresas"
                    description={companiesSummary(counts.all, trialsEndingSoon)}
                >
                    {/* Support sees the console and enters companies; only the superadmin changes them. */}
                    {auth.isSuperadmin && (
                        <CreateTenantDialog
                            // A new request opens it again, filled.
                            key={prefill?.demo_request_id ?? 'new'}
                            prefill={prefill}
                        />
                    )}
                </PageHeader>
                {filtered && (
                    <TenantsToolbar filters={filters} counts={counts} />
                )}
                {tenants.data.length === 0 ? (
                    <TenantsEmpty filtered={filtered} filters={filters} />
                ) : (
                    <TenantsTable
                        tenants={tenants.data}
                        activationMinDate={activationMinDate}
                    />
                )}
                <Pagination
                    previous="Ver más recientes"
                    next="Ver más antiguas"
                    page={tenants}
                    label="Páginas de empresas"
                />
            </div>
        </>
    );
}
