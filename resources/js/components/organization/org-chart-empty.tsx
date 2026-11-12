import { Link } from '@inertiajs/react';
import { Network, Users } from 'lucide-react';
import EmptyState from '@/components/humetria/empty-state';
import { Button } from '@/components/ui/button';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { index as employees } from '@/routes/tenant/employees';

/** A company just loaded: nobody reports to anyone yet. Whoever may change the chart gets the two ways to start. */
export default function OrgChartEmpty({ canManage }: { canManage: boolean }) {
    const tenantSlug = useTenantSlug();

    return (
        <EmptyState
            icon={Network}
            title="Todavía nadie tiene jefe directo"
            action={
                canManage ? (
                    <Button asChild>
                        <Link href={employees(tenantSlug)}>
                            <Users />
                            Ir a Empleados
                        </Link>
                    </Button>
                ) : undefined
            }
        >
            Asígnalo al editar a cada persona o súbelo en el CSV de empleados.
        </EmptyState>
    );
}
