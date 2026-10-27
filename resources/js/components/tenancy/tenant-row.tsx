import { Badge } from '@/components/ui/badge';
import { TableCell, TableRow } from '@/components/ui/table';
import { tenantStatusVariant } from '@/lib/tenant-status';
import type { TenantRow as Tenant } from '@/types';

export default function TenantRowView({ tenant }: { tenant: Tenant }) {
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
        </TableRow>
    );
}
