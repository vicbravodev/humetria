import { Head, usePage } from '@inertiajs/react';
import { TenantBlockedDialog } from '@/components/tenant-blocked-dialog';

/** What a suspended company's pages show: the modal over the empty frame. It has no props, only the shared ones. */
export default function Blocked() {
    const { tenant, tenants } = usePage().props;

    return (
        <>
            <Head title="Acceso en pausa" />
            {tenant !== null && (
                <TenantBlockedDialog
                    tradeName={tenant.trade_name}
                    canSwitch={tenants.length > 1}
                    dismissible={false}
                />
            )}
        </>
    );
}
