import { Link, useForm } from '@inertiajs/react';
import { CalendarClock, Power, Printer, RefreshCw } from 'lucide-react';
import { useState } from 'react';
import ExpiryDialog from '@/components/disclosure/expiry-dialog';
import ConfirmDialog from '@/components/humetria/confirm-dialog';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import {
    disable,
    enable,
    poster,
    rotate,
} from '@/routes/tenant/disclosure/page';
import type { PageLinkData } from '@/types';

/**
 * What RH can do with the link: switch the page off and on (the poster stays), set its last day, or replace it (the old one
 * stops opening at once, so the printed posters have to change).
 */
export default function PageLinkActions({ link }: { link: PageLinkData }) {
    const tenantSlug = useTenantSlug();
    const toggle = useForm({});
    const [expiring, setExpiring] = useState(false);
    const off = link.state.value === 'disabled';

    return (
        <div className="flex flex-wrap items-center gap-2">
            <Button
                variant="outline"
                disabled={toggle.processing}
                onClick={() =>
                    toggle.post((off ? enable : disable).url(tenantSlug), {
                        preserveScroll: true,
                    })
                }
            >
                {toggle.processing ? <Spinner /> : <Power aria-hidden />}
                {off ? 'Activar la página' : 'Desactivar la página'}
            </Button>
            {link.url !== null && (
                <Button asChild variant="outline">
                    <Link href={poster.url(tenantSlug)}>
                        <Printer aria-hidden />
                        Cartel imprimible
                    </Link>
                </Button>
            )}
            <Button variant="outline" onClick={() => setExpiring(true)}>
                <CalendarClock aria-hidden />
                Vencimiento
            </Button>
            <ConfirmDialog
                trigger={
                    <Button variant="outline">
                        <RefreshCw aria-hidden />
                        Generar un enlace nuevo
                    </Button>
                }
                title="¿Generar un enlace nuevo?"
                description="El enlace actual deja de abrir al instante. Úsalo si se filtró a personas que ya no trabajan aquí. Tendrás que imprimir de nuevo los carteles y reenviar el enlace."
                confirmLabel="Generar enlace nuevo"
                form={rotate.form(tenantSlug)}
                preserveScroll
            />
            {expiring && (
                <ExpiryDialog
                    open={expiring}
                    onOpenChange={setExpiring}
                    link={link}
                />
            )}
        </div>
    );
}
