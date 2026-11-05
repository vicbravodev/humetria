import { Link } from '@inertiajs/react';
import { FileText } from 'lucide-react';
import EmptyState from '@/components/humetria/empty-state';
import { Button } from '@/components/ui/button';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { write } from '@/routes/tenant/disclosure';

/** Before there is a policy: what it is for and the one step that writes it (board V2-N035-Politica). */
export default function PolicyStart({
    canManage,
    drafting,
}: {
    canManage: boolean;
    /** Its questions started and were left: it goes on where it was. */
    drafting: boolean;
}) {
    const tenantSlug = useTenantSlug();

    return (
        <EmptyState
            icon={FileText}
            title={
                drafting ? 'Tu política va a medias' : 'Aún no hay una política'
            }
            action={
                canManage ? (
                    <Button asChild>
                        <Link href={write.url([tenantSlug, 'policy'])}>
                            {drafting ? 'Continuar' : 'Escribir con preguntas'}
                        </Link>
                    </Button>
                ) : undefined
            }
        >
            {drafting
                ? 'Sigue donde te quedaste: cada respuesta ya está guardada.'
                : 'La norma pide una política de prevención por escrito (numeral 5.1). Contestas 6 preguntas, unos 10 minutos, y sale del ejemplo de la Guía de referencia IV con lo que es cierto en tus centros.'}
        </EmptyState>
    );
}
