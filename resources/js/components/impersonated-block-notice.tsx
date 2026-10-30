import { usePage } from '@inertiajs/react';
import { TenantBlockedDialog } from '@/components/tenant-blocked-dialog';

/**
 * A blocked company only reaches the panel for a superadmin who is impersonating (BlockSuspendedTenant lets them
 * through): they get the same notice as its members, but one they can close.
 */
export function ImpersonatedBlockNotice() {
    const { tenant, tenants, impersonator } = usePage().props;

    if (!tenant?.blocked || impersonator === null) {
        return null;
    }

    return (
        <TenantBlockedDialog
            tradeName={tenant.trade_name}
            canSwitch={tenants.total > 1}
            impersonating
        />
    );
}
