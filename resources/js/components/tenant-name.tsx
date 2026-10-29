import { trialLabel } from '@/lib/tenant-status';
import type { CurrentTenant } from '@/types';

export function TenantName({ tenant }: { tenant: CurrentTenant }) {
    return (
        <span className="grid min-w-0 flex-1 leading-tight group-data-[collapsible=icon]:hidden">
            <span className="truncate font-semibold">{tenant.trade_name}</span>
            {tenant.trial_days_left !== null && (
                <span className="truncate text-sm text-muted-foreground">
                    {trialLabel(tenant.trial_days_left)}
                </span>
            )}
        </span>
    );
}
