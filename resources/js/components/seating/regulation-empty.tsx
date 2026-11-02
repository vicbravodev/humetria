import { Form } from '@inertiajs/react';
import { Factory } from 'lucide-react';
import EmptyState from '@/components/humetria/empty-state';
import { Button } from '@/components/ui/button';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { store } from '@/routes/tenant/seating/regulation';

/** Before the draft exists: what it will be made of, and the button that prepares it for who manages. */
export default function RegulationEmpty({
    workCenterId,
    canManage,
}: {
    workCenterId: string;
    canManage: boolean;
}) {
    const tenantSlug = useTenantSlug();

    return (
        <EmptyState
            icon={Factory}
            title="Todavía no hay borrador"
            action={
                canManage ? (
                    <Form {...store.form([tenantSlug, workCenterId])}>
                        {({ processing }) => (
                            <Button disabled={processing}>
                                Preparar borrador con los datos del centro
                            </Button>
                        )}
                    </Form>
                ) : undefined
            }
        >
            Con los puestos, las pausas acordadas y los asientos del centro
            armamos un borrador de las tres partes y la norma de uso. Tú lo
            editas.
        </EmptyState>
    );
}
