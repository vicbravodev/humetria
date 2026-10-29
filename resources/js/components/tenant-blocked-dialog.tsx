import { Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
} from '@/components/ui/dialog';
import { dashboard, logout } from '@/routes';
import { stop } from '@/routes/impersonation';

/**
 * Tells a person their company's access is paused. Open from the start; it only goes away for a superadmin who is
 * `impersonating`, whose way out is ending the impersonation, not the owner's sign-out. For everyone else Esc and a
 * click outside leave it as it is, and it has no close button.
 */
export function TenantBlockedDialog({
    tradeName,
    canSwitch,
    impersonating,
}: {
    tradeName: string;
    canSwitch: boolean;
    impersonating: boolean;
}) {
    const [open, setOpen] = useState(true);
    const keepOpen = (event: Event) => {
        if (!impersonating) {
            event.preventDefault();
        }
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogContent
                className={impersonating ? undefined : '[&>button]:hidden'}
                onEscapeKeyDown={keepOpen}
                onPointerDownOutside={keepOpen}
                onInteractOutside={keepOpen}
                data-test="tenant-blocked-dialog"
            >
                <DialogTitle>El acceso a {tradeName} está en pausa</DialogTitle>
                <DialogDescription>
                    Puede deberse a un pago pendiente o a que terminó tu periodo
                    de prueba o de servicio. Para reactivarlo, contacta al
                    administrador de Humetría.
                </DialogDescription>
                <DialogFooter>
                    {canSwitch && (
                        <Button asChild variant="outline">
                            <Link href={dashboard()}>Cambiar de empresa</Link>
                        </Button>
                    )}
                    <Button asChild>
                        {impersonating ? (
                            <Link
                                href={stop()}
                                as="button"
                                onClick={() => router.flushAll()}
                                data-test="stop-impersonation"
                            >
                                Salir
                            </Link>
                        ) : (
                            <Link
                                href={logout()}
                                as="button"
                                onClick={() => router.flushAll()}
                                data-test="logout-button"
                            >
                                Cerrar sesión
                            </Link>
                        )}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
