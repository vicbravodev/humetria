import { Link, usePage } from '@inertiajs/react';
import Initials from '@/components/humetria/initials';
import TenantOwnerCell from '@/components/tenancy/tenant-owner-cell';
import TenantRowActions from '@/components/tenancy/tenant-row-actions';
import TenantSupportActions from '@/components/tenancy/tenant-support-actions';
import { Badge } from '@/components/ui/badge';
import { TableCell, TableRow } from '@/components/ui/table';
import { tenantStatusVariant } from '@/lib/tenant-status';
import { show } from '@/routes/companies';
import type { TenantRow as Tenant } from '@/types';

/** A company of the console (pantallas: Consola-Empresas): its names, RFC, status with its date and owner. */
export default function TenantRowView({
    tenant,
    activationMinDate,
}: {
    tenant: Tenant;
    activationMinDate: string;
}) {
    const { auth } = usePage().props;

    return (
        <TableRow>
            <TableCell className="min-w-60 whitespace-normal">
                <div className="flex items-center gap-2.5">
                    <Initials name={tenant.trade_name} kind="company" />
                    <div className="flex min-w-0 flex-col">
                        <Link
                            href={show(tenant.id)}
                            className="font-medium underline-offset-4 hover:underline"
                        >
                            {tenant.trade_name}
                        </Link>
                        <span className="text-caption text-muted-foreground">
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
                        <span className="text-caption text-muted-foreground">
                            {tenant.status_date}
                        </span>
                    )}
                    {tenant.restriction_reason !== null && (
                        <span className="max-w-64 text-caption text-muted-foreground">
                            Motivo: {tenant.restriction_reason}
                        </span>
                    )}
                </div>
            </TableCell>
            <TableCell className="whitespace-normal">
                <TenantOwnerCell tenant={tenant} />
            </TableCell>
            <TableCell className="w-px text-right">
                {auth.isSuperadmin ? (
                    <TenantRowActions
                        tenant={tenant}
                        activationMinDate={activationMinDate}
                    />
                ) : (
                    <TenantSupportActions tenant={tenant} />
                )}
            </TableCell>
        </TableRow>
    );
}
