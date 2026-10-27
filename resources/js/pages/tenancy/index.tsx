import { Head } from '@inertiajs/react';
import ConsolePagination from '@/components/console-pagination';
import PageHeader from '@/components/page-header';
import TenantsEmpty from '@/components/tenancy/tenants-empty';
import TenantsTable from '@/components/tenancy/tenants-table';
import type { Paginated, TenantRow } from '@/types';

export default function Tenants({
    tenants,
}: {
    tenants: Paginated<TenantRow>;
}) {
    return (
        <>
            <Head title="Empresas" />
            <div className="flex flex-col gap-8 px-4 py-6 md:px-10 md:py-8">
                <PageHeader caption="Plataforma" title="Empresas">
                    <p className="text-muted-foreground">
                        {tenants.total === 1
                            ? '1 empresa'
                            : `${tenants.total} empresas`}
                    </p>
                </PageHeader>
                {tenants.data.length === 0 ? (
                    <TenantsEmpty />
                ) : (
                    <TenantsTable tenants={tenants.data} />
                )}
                <ConsolePagination page={tenants} label="Páginas de empresas" />
            </div>
        </>
    );
}
