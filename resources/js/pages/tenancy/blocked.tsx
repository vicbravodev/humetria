import { Head, usePage } from '@inertiajs/react';
import { Lock } from 'lucide-react';
import StatusMessage from '@/components/humetria/status-message';
import { TenantBlockedDialog } from '@/components/tenant-blocked-dialog';

/**
 * What a suspended company's pages show: the modal over a quiet notice in the language of the error pages (pantallas:
 * Error-403), the empty arch with a lock. It has no props, only the shared ones.
 */
export default function Blocked() {
    const { tenant, tenants } = usePage().props;

    return (
        <>
            <Head title="Acceso en pausa" />
            <div className="flex justify-center px-4 py-16 md:py-24">
                <StatusMessage icon={Lock} title="Acceso en pausa">
                    Mientras se reactiva, la información de la empresa se
                    conserva tal como está.
                </StatusMessage>
            </div>
            {tenant !== null && (
                <TenantBlockedDialog
                    tradeName={tenant.trade_name}
                    canSwitch={tenants.total > 1}
                    impersonating={false}
                />
            )}
        </>
    );
}
