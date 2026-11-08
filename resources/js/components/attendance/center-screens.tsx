import { router, usePage } from '@inertiajs/react';
import { MonitorSmartphone, Plus } from 'lucide-react';
import KioskLinkPanel from '@/components/attendance/kiosk-link-panel';
import KioskRow from '@/components/attendance/kiosk-row';
import EmptyState from '@/components/humetria/empty-state';
import Pagination from '@/components/humetria/pagination';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { store } from '@/routes/tenant/attendance/kiosks';
import type { KioskScreen, Paginated } from '@/types';

/** A center's QR screens: a fixed tablet shows a code that changes; whoever marks scans it. Issued, seen and revoked here. */
export default function CenterScreens({
    kiosks,
    centerId,
    canManage,
}: {
    kiosks: Paginated<KioskScreen>;
    centerId: string;
    canManage: boolean;
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
                        Una tableta fija muestra un código que cambia cada 30 s:
                        quien marca lo escanea con su teléfono.
                    </p>
                </div>
                {kiosks.total > 0 && issue}
            </div>
            {newLink !== undefined && <KioskLinkPanel link={newLink} />}
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
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Estado</TableHead>
                                <TableHead>Emitida</TableHead>
                                <TableHead>Última señal</TableHead>
                                {canManage && (
                                    <TableHead>
                                        <span className="sr-only">
                                            Acciones
                                        </span>
                                    </TableHead>
                                )}
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {kiosks.data.map((screen) => (
                                <KioskRow
                                    key={screen.id}
                                    screen={screen}
                                    canManage={canManage}
                                />
                            ))}
                        </TableBody>
                    </Table>
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
