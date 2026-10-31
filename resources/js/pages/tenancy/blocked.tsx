import { Head, usePage } from '@inertiajs/react';
import { Lock } from 'lucide-react';
import ArcMeter from '@/components/humetria/arc-meter';
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
            <section className="flex flex-col items-center gap-3 px-4 py-16 text-center md:py-24">
                <ArcMeter value={0} max={1} label="Acceso en pausa" size="sm">
                    <Lock aria-hidden className="mb-1 size-5 text-primary" />
                </ArcMeter>
                <h1 className="text-3xl leading-[38px] font-semibold tracking-[-0.01em]">
                    Acceso en pausa
                </h1>
                <p className="max-w-md text-[17px] leading-6 text-muted-foreground">
                    Mientras se reactiva, la información de la empresa se
                    conserva tal como está.
                </p>
            </section>
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
