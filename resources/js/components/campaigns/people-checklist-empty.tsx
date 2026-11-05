import { Link } from '@inertiajs/react';
import { Users } from 'lucide-react';
import EmptyState from '@/components/humetria/empty-state';
import { Button } from '@/components/ui/button';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { index as employees } from '@/routes/tenant/employees';

/** When a search finds nobody, or nobody works in the center yet and there is no one to send the Guía I to (C7). */
export default function PeopleChecklistEmpty({
    workCenter,
    searched,
}: {
    workCenter: string;
    searched: boolean;
}) {
    const tenantSlug = useTenantSlug();

    if (searched) {
        return (
            <EmptyState icon={Users} title="Nadie coincide con la búsqueda">
                Prueba con otro nombre, correo o número.
            </EmptyState>
        );
    }

    return (
        <EmptyState
            icon={Users}
            title={`Nadie trabaja en ${workCenter} todavía`}
            action={
                <Button asChild variant="outline">
                    <Link href={employees(tenantSlug)}>Registrar personas</Link>
                </Button>
            }
        >
            La Guía I se envía a las personas registradas en el centro.
        </EmptyState>
    );
}
