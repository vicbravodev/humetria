import { Badge } from '@/components/ui/badge';
import { tenantStatusVariant } from '@/lib/tenant-status';
import type { TenantRow } from '@/types';

/** A company's status in its row (tablero Empresas): one label; its date and reason are in the quick view and on hover. */
export default function TenantStatus({ tenant }: { tenant: TenantRow }) {
    const detail = [
        tenant.status_date,
        tenant.restriction_reason === null
            ? null
            : `Motivo: ${tenant.restriction_reason}`,
    ]
        .filter((line) => line !== null)
        .join(' · ');

    return (
        <Badge
            variant={tenantStatusVariant(tenant.status)}
            title={detail === '' ? undefined : detail}
        >
            {tenant.status_label}
        </Badge>
    );
}
