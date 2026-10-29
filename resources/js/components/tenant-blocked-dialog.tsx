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

/**
 * Tells a person their company's access is paused. Open from the start; it only goes away when `dismissible`
 * (a superadmin who is impersonating): otherwise Esc and a click outside leave it as it is, and it has no close button.
 */
export function TenantBlockedDialog({
    tradeName,
    canSwitch,
    dismissible,
}: {
    tradeName: string;
    canSwitch: boolean;
    dismissible: boolean;
}) {
    const [open, setOpen] = useState(true);
    const keepOpen = (event: Event) => {
        if (!dismissible) {
            event.preventDefault();
        }
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogContent
                className={dismissible ? undefined : '[&>button]:hidden'}
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
                        <Link
                            href={logout()}
                            as="button"
                            onClick={() => router.flushAll()}
                            data-test="logout-button"
                        >
                            Cerrar sesión
                        </Link>
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
