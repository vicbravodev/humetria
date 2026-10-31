import Initials from '@/components/humetria/initials';
import { trialLabel } from '@/lib/tenant-status';
import type { CurrentTenant } from '@/types';

/** The company the panel works in, as the sidebar card shows it: its initials, its name and, in a trial, the days left. */
export function TenantName({ tenant }: { tenant: CurrentTenant }) {
    return (
        <>
            <Initials name={tenant.trade_name} kind="company" />
            <span className="grid min-w-0 flex-1 leading-tight group-data-[collapsible=icon]:sr-only">
                <span className="truncate text-[15px] font-semibold">
                    {tenant.trade_name}
                </span>
                {tenant.trial_days_left !== null && (
                    <span className="text-[13px] leading-[18px] text-muted-foreground">
                        {trialLabel(tenant.trial_days_left)}
                    </span>
                )}
            </span>
        </>
    );
}
