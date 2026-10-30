import Initials from '@/components/humetria/initials';
import TenantOwnerCell from '@/components/tenancy/tenant-owner-cell';
import TenantRowActions from '@/components/tenancy/tenant-row-actions';
import { Badge } from '@/components/ui/badge';
import { TableCell, TableRow } from '@/components/ui/table';
import { tenantStatusVariant } from '@/lib/tenant-status';
import type { TenantRow as Tenant } from '@/types';

/** A company of the console (pantallas: Consola-Empresas): its names, RFC, status with its date and owner. */
export default function TenantRowView({
    tenant,
    activationMinDate,
}: {
    tenant: Tenant;
    activationMinDate: string;
}) {
    return (
        <TableRow>
            <TableCell className="min-w-60 whitespace-normal">
                <div className="flex items-center gap-2.5">
                    <Initials name={tenant.trade_name} kind="company" />
                    <div className="flex min-w-0 flex-col">
                        <span className="font-medium">{tenant.trade_name}</span>
                        <span className="text-[13px] leading-[18px] text-muted-foreground">
                            {tenant.legal_name}
                        </span>
                    </div>
                </div>
            </TableCell>
            <TableCell className="text-sm tabular-nums">{tenant.rfc}</TableCell>
            <TableCell className="whitespace-normal">
                <div className="flex flex-col items-start gap-1">
                    <Badge variant={tenantStatusVariant(tenant.status)}>
                        {tenant.status_label}
                    </Badge>
                    {tenant.status_date !== null && (
                        <span className="text-[13px] leading-[18px] text-muted-foreground">
                            {tenant.status_date}
                        </span>
                    )}
                </div>
            </TableCell>
            <TableCell className="whitespace-normal">
                <TenantOwnerCell tenant={tenant} />
            </TableCell>
            <TableCell className="w-px text-right">
                <TenantRowActions
                    tenant={tenant}
                    activationMinDate={activationMinDate}
                />
            </TableCell>
        </TableRow>
    );
}
