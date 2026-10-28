import ActivateTenantDialog from '@/components/tenancy/activate-tenant-dialog';
import DeactivateTenantDialog from '@/components/tenancy/deactivate-tenant-dialog';
import EditTenantDialog from '@/components/tenancy/edit-tenant-dialog';
import InviteOwnerDialog from '@/components/tenancy/invite-owner-dialog';
import TenantOwnerCell from '@/components/tenancy/tenant-owner-cell';
import { Badge } from '@/components/ui/badge';
import { TableCell, TableRow } from '@/components/ui/table';
import { tenantStatusVariant } from '@/lib/tenant-status';
import type { TenantRow as Tenant } from '@/types';

export default function TenantRowView({
    tenant,
    activationMinDate,
}: {
    tenant: Tenant;
    activationMinDate: string;
}) {
    return (
        <TableRow>
            <TableCell className="whitespace-normal">
                <p>{tenant.trade_name}</p>
                <p className="text-sm text-muted-foreground">
                    {tenant.legal_name}
                </p>
            </TableCell>
            <TableCell className="text-sm tabular-nums">{tenant.rfc}</TableCell>
            <TableCell className="whitespace-normal">
                <Badge variant={tenantStatusVariant(tenant.status)}>
                    {tenant.status_label}
                </Badge>
                {tenant.status_date !== null && (
                    <p className="mt-1 text-sm text-muted-foreground">
                        {tenant.status_date}
                    </p>
                )}
            </TableCell>
            <TableCell className="whitespace-normal">
                <TenantOwnerCell tenant={tenant} />
            </TableCell>
            <TableCell className="text-right">
                <div className="flex justify-end gap-2">
                    <ActivateTenantDialog
                        tenant={tenant}
                        minDate={activationMinDate}
                    />
                    <InviteOwnerDialog tenant={tenant} />
                    <EditTenantDialog tenant={tenant} />
                    {!tenant.blocked && (
                        <DeactivateTenantDialog tenant={tenant} />
                    )}
                </div>
            </TableCell>
        </TableRow>
    );
}
