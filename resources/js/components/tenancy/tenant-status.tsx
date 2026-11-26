import { Badge } from '@/components/ui/badge';
import { tenantStatusVariant } from '@/lib/tenant-status';
import type { TenantRow } from '@/types';

/** A company's status with its date and, when restricted, why. */
export default function TenantStatus({ tenant }: { tenant: TenantRow }) {
    return (
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
    );
}
