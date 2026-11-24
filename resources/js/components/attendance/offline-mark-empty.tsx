import { Link } from '@inertiajs/react';
import { CloudOff } from 'lucide-react';
import EmptyState from '@/components/humetria/empty-state';
import { Button } from '@/components/ui/button';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { correctionsQuery } from '@/lib/attendance/corrections';
import { index } from '@/routes/tenant/attendance/corrections';

/** What the list says when it has no marks: nothing waits, or nothing matches what was searched or filtered. */
export default function OfflineMarkEmpty({
    q,
    unflagged,
}: {
    q: string;
    unflagged: boolean;
}) {
    const tenantSlug = useTenantSlug();

    return (
        <EmptyState
            icon={CloudOff}
            title={
                q !== '' || unflagged
                    ? 'Nada con ese filtro'
                    : 'Nada por verificar'
            }
            action={
                unflagged ? (
                    <Button asChild variant="outline">
                        <Link
                            href={index(tenantSlug, {
                                query: correctionsQuery({
                                    q,
                                    status: 'offline',
                                }),
                            })}
                        >
                            Quitar el filtro
                        </Link>
                    </Button>
                ) : undefined
            }
        >
            {q !== ''
                ? 'Revisa cómo lo escribiste o busca con otra parte del nombre.'
                : 'No hay marcas sin conexión por verificar. Las que lleguen aparecerán aquí.'}
        </EmptyState>
    );
}
