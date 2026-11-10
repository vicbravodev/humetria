import { router, usePage } from '@inertiajs/react';
import { MonitorSmartphone, Plus } from 'lucide-react';
import KiosksTable from '@/components/attendance/kiosks-table';
import ScreenSteps from '@/components/attendance/screen-steps';
import OneTimeLink from '@/components/attendance/one-time-link';
import EmptyState from '@/components/humetria/empty-state';
import Pagination from '@/components/humetria/pagination';
import { Button } from '@/components/ui/button';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { store } from '@/routes/tenant/attendance/kiosks';
import type { KioskScreen, Paginated } from '@/types';

/** A center's QR screens: a fixed tablet shows a code that changes; whoever marks scans it. Issued, seen and revoked here. */
export default function CenterScreens({
    kiosks,
    centerId,
    canManage,
    qrSeconds,
    active,
}: {
    kiosks: Paginated<KioskScreen>;
    centerId: string;
    canManage: boolean;
    qrSeconds: number;
    /** How many of the screens show the code. */
    active: number;
}) {
    const tenantSlug = useTenantSlug();
    const newLink = usePage().flash.kioskLink;
    const issue = canManage ? (
        <Button
            onClick={() =>
                router.post(
                    store.url(tenantSlug),
                    { work_center_id: centerId },
                    { preserveScroll: true },
                )
            }
        >
            <Plus aria-hidden />
            Emitir pantalla
        </Button>
    ) : undefined;

    return (
        <section aria-labelledby="pantallas" className="flex flex-col gap-4">
            <div className="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h2
                        id="pantallas"
                        className="text-xl leading-7 font-semibold"
                    >
                        Pantallas del QR
                    </h2>
                    <p className="text-sm text-muted-foreground">
                        {`Una tableta fija muestra un código que cambia cada ${qrSeconds} s: quien marca lo escanea con su teléfono.`}
                    </p>
                </div>
                {kiosks.total > 0 && issue}
            </div>
            {newLink !== undefined && (
                <OneTimeLink
                    id="kiosk-link"
                    label="Enlace de la pantalla"
                    link={newLink}
                >
                    Ábrelo en la tableta que mostrará el código: funciona una
                    sola vez y no se vuelve a mostrar. Si un chat o un correo lo
                    abre antes (al mostrar su vista previa), vuelve a emitirlo.
                </OneTimeLink>
            )}
            {kiosks.total === 0 ? (
                <EmptyState
                    icon={MonitorSmartphone}
                    title="Sin pantallas del QR"
                    action={issue}
                >
                    Emite una y abre su enlace en la tableta de la entrada:
                    mostrará el código del centro sin cuenta ni contraseña.
                </EmptyState>
            ) : (
                <>
                    {kiosks.data.some(
                        (screen) => screen.state === 'issued',
                    ) && <ScreenSteps />}
                    <KiosksTable
                        kiosks={kiosks.data}
                        canManage={canManage}
                        active={active}
                    />
                    <Pagination
                        page={kiosks}
                        label="Páginas de pantallas"
                        pinned={false}
                    />
                </>
            )}
        </section>
    );
}
