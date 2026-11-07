import { Link, usePage } from '@inertiajs/react';
import { Building2, Users } from 'lucide-react';
import EmptyState from '@/components/humetria/empty-state';
import { Button } from '@/components/ui/button';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { index as employees } from '@/routes/tenant/employees';
import { index as workCenters } from '@/routes/tenant/work-centers';

/** A company with nobody to count yet: the next step is a center, then its people. */
export default function OverviewEmpty({ hasCenters }: { hasCenters: boolean }) {
    const tenantSlug = useTenantSlug();
    const canAddPeople =
        usePage().props.tenant?.can.manage_organization === true;

    if (!hasCenters) {
        return (
            <EmptyState
                icon={Building2}
                title="Aún no hay centros de trabajo"
                action={
                    <Button asChild>
                        <Link href={workCenters(tenantSlug)}>
                            Ir a centros de trabajo
                        </Link>
                    </Button>
                }
            >
                El registro de jornada se lleva por centro: da de alta el
                primero y luego a sus personas.
            </EmptyState>
        );
    }

    return (
        <EmptyState
            icon={Users}
            title="Aún no hay personas en tus centros"
            action={
                canAddPeople ? (
                    <Button asChild>
                        <Link href={employees(tenantSlug)}>
                            Agregar personas
                        </Link>
                    </Button>
                ) : undefined
            }
        >
            Cuando des de alta a las personas de tus centros, aquí verás cada
            semana quién la confirmó y sus horas.
        </EmptyState>
    );
}
