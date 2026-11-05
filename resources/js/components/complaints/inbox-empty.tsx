import { Link } from '@inertiajs/react';
import { Inbox } from 'lucide-react';
import EmptyState from '@/components/humetria/empty-state';
import { Button } from '@/components/ui/button';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { show } from '@/routes/tenant/complaints/channel';

/** The inbox without cases (board V4-Quejas-Bandeja, «sin casos»): that an email comes when one does, and the next step. */
export default function InboxEmpty({
    closed,
    filtered,
    manages,
}: {
    closed: boolean;
    filtered: boolean;
    manages: boolean;
}) {
    const tenantSlug = useTenantSlug();

    if (filtered) {
        return (
            <EmptyState icon={Inbox} title="Nadie coincide">
                Ningún caso tuyo tiene esa referencia, ese tipo o ese centro.
            </EmptyState>
        );
    }

    if (closed) {
        return (
            <EmptyState icon={Inbox} title="Sin casos cerrados">
                Los casos que cierres quedan aquí, sin su contenido, como
                constancia de que el canal opera.
            </EmptyState>
        );
    }

    return (
        <EmptyState
            icon={Inbox}
            title="No tienes casos abiertos"
            action={
                manages ? (
                    <Button variant="outline" asChild>
                        <Link href={show(tenantSlug)}>Revisar el canal</Link>
                    </Button>
                ) : undefined
            }
        >
            Cuando llegue uno te avisamos por correo, sin detalles del caso.
            {manages && ' Mientras tanto, revisa que el canal esté completo.'}
        </EmptyState>
    );
}
