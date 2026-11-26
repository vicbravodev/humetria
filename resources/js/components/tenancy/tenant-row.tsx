import { usePage } from '@inertiajs/react';
import TenantNameCell from '@/components/tenancy/tenant-name-cell';
import TenantStatus from '@/components/tenancy/tenant-status';
import TenantSelectCell from '@/components/tenancy/tenant-select-cell';
import TenantRowActions from '@/components/tenancy/tenant-row-actions';
import TenantQuickView, {
    type TenantPeek,
} from '@/components/tenancy/tenant-quick-view';
import TenantSupportActions from '@/components/tenancy/tenant-support-actions';
import TenantWorkCells from '@/components/tenancy/tenant-work-cells';
import { TableCell, TableRow } from '@/components/ui/table';
import type { IdSelection } from '@/hooks/use-id-selection';
import { useIsMobile } from '@/hooks/use-mobile';
import type { TenantRow as Tenant } from '@/types';

/** A company of the console (pantallas: Consola-Empresas): its names, RFC, status with its date and owner. */
export default function TenantRowView({
    tenant,
    activationMinDate,
    selection,
    peek,
}: {
    tenant: Tenant;
    activationMinDate: string;
    selection: IdSelection | null;
    peek: TenantPeek | null;
}) {
    const { auth } = usePage().props;
    // On a phone the status rides under the name, so the row's buttons stay in reach.
    const mobile = useIsMobile();

    return (
        <TableRow
            data-state={
                selection?.isChosen(tenant.id) === true ||
                peek?.id === tenant.id
                    ? 'selected'
                    : undefined
            }
        >
            <TenantSelectCell tenant={tenant} selection={selection} />
            <TenantNameCell tenant={tenant} mobile={mobile} />
            <TableCell className="whitespace-normal max-md:hidden">
                <TenantStatus tenant={tenant} />
            </TableCell>
            <TenantWorkCells tenant={tenant} />
            <TableCell className="hidden text-sm whitespace-nowrap tabular-nums min-[1500px]:table-cell min-[1600px]:hidden">
                {tenant.last_access ?? (
                    <span className="text-muted-foreground">Nunca</span>
                )}
            </TableCell>
            <TableCell className="w-px text-right max-md:px-2">
                <div className="flex items-center justify-end gap-1">
                    <TenantQuickView tenant={tenant} peek={peek} />
                    {auth.isSuperadmin ? (
                        <TenantRowActions
                            tenant={tenant}
                            activationMinDate={activationMinDate}
                        />
                    ) : (
                        <TenantSupportActions
                            tenantId={tenant.id}
                            tradeName={tenant.trade_name}
                            hasOwner={tenant.owner !== null}
                        />
                    )}
                </div>
            </TableCell>
        </TableRow>
    );
}
