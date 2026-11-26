import { usePage } from '@inertiajs/react';
import { useState } from 'react';
import TenantPeekPanel from '@/components/tenancy/tenant-peek-panel';
import TenantsBulkBar from '@/components/tenancy/tenants-bulk-bar';
import TenantsTable from '@/components/tenancy/tenants-table';
import { useIdSelection } from '@/hooks/use-id-selection';
import { useMediaQuery } from '@/hooks/use-media-query';
import type { StaffMember, TenantRow } from '@/types';

/**
 * The companies of a page with what the staff does with them (tablero Empresas): the table, the bar for the ones chosen
 * and, on a wide screen, the quick view of one docked beside it; narrower, the quick view opens as a sheet.
 */
export default function TenantsWorkArea({
    tenants,
    activationMinDate,
    staff,
}: {
    tenants: TenantRow[];
    activationMinDate: string;
    staff: StaffMember[];
}) {
    const { auth } = usePage().props;
    const selection = useIdSelection(tenants.map((tenant) => tenant.id));
    const wide = useMediaQuery('(min-width: 1600px)');
    const [peekId, setPeekId] = useState<string | null>(null);
    const peeked = tenants.find((tenant) => tenant.id === peekId) ?? tenants[0];

    return (
        <div className="flex items-start gap-6">
            <div className="flex min-w-0 flex-1 flex-col gap-2">
                <TenantsTable
                    tenants={tenants}
                    activationMinDate={activationMinDate}
                    selection={auth.isSuperadmin ? selection : null}
                    peek={
                        wide && peeked !== undefined
                            ? { id: peeked.id, choose: setPeekId }
                            : null
                    }
                />
                {auth.isSuperadmin && (
                    <TenantsBulkBar selection={selection} staff={staff} />
                )}
            </div>
            {wide && peeked !== undefined && (
                <TenantPeekPanel tenant={peeked} />
            )}
        </div>
    );
}
