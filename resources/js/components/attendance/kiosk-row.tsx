import ConfirmDialog from '@/components/humetria/confirm-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { TableCell, TableRow } from '@/components/ui/table';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { shortMoment } from '@/lib/attendance/corrections';
import {
    revokeScreenText,
    screenBadge,
    screenSeen,
} from '@/lib/attendance/kiosk-screens';
import { destroy, reissue } from '@/routes/tenant/attendance/kiosks';
import type { KioskScreen } from '@/types';

/** A QR screen: its state, when it was issued and last seen; issued again while never seen, revoked while not yet. */
export default function KioskRow({
    screen,
    canManage,
    active,
}: {
    screen: KioskScreen;
    canManage: boolean;
    /** How many screens of the center show the code. */
    active: number;
}) {
    const tenantSlug = useTenantSlug();
    const route = { tenant: tenantSlug, kiosk: screen.id };
    const badge = screenBadge(screen.state);

    return (
        <TableRow>
            <TableCell>
                <Badge variant={badge.variant}>{badge.label}</Badge>
            </TableCell>
            <TableCell className="tabular-nums">
                {shortMoment(screen.issuedAt)}
            </TableCell>
            <TableCell className="text-muted-foreground">
                {screenSeen(screen)}
            </TableCell>
            {canManage && (
                <TableCell className="text-right whitespace-nowrap">
                    {screen.state === 'issued' && (
                        <ConfirmDialog
                            trigger={
                                <Button variant="ghost" size="sm">
                                    Volver a emitir
                                </Button>
                            }
                            title="¿Emitir un enlace nuevo?"
                            description="El enlace anterior deja de funcionar, aunque alguien ya lo haya abierto. El nuevo se muestra una sola vez."
                            confirmLabel="Emitir enlace"
                            form={reissue.form(route)}
                            preserveScroll
                            destructive={false}
                        />
                    )}
                    {screen.state !== 'revoked' && (
                        <ConfirmDialog
                            trigger={
                                <Button variant="ghost" size="sm">
                                    Revocar
                                </Button>
                            }
                            title="¿Revocar esta pantalla?"
                            description={revokeScreenText(screen.state, active)}
                            confirmLabel="Revocar pantalla"
                            form={destroy.form(route)}
                            preserveScroll
                        />
                    )}
                </TableCell>
            )}
        </TableRow>
    );
}
