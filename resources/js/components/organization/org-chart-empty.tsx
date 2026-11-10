import { Link } from '@inertiajs/react';
import { Network, Upload, Users } from 'lucide-react';
import EmptyState from '@/components/humetria/empty-state';
import { Button } from '@/components/ui/button';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { index as employees } from '@/routes/tenant/employees';
import { create as importPage } from '@/routes/tenant/employees/import';

/** A company just loaded: nobody reports to anyone yet. Whoever may change the chart gets the two ways to start. */
export default function OrgChartEmpty({ canManage }: { canManage: boolean }) {
    const tenantSlug = useTenantSlug();

    return (
        <EmptyState
            icon={Network}
            title="Todavía nadie tiene jefe asignado."
            action={
                canManage ? (
                    <div className="flex flex-wrap justify-center gap-2">
                        <Button variant="outline" asChild>
                            <Link href={importPage(tenantSlug)}>
                                <Upload />
                                Importar CSV
                            </Link>
                        </Button>
                        <Button asChild>
                            <Link href={employees(tenantSlug)}>
                                <Users />
                                Ir a Empleados
                            </Link>
                        </Button>
                    </div>
                ) : undefined
            }
        >
            Asigna el jefe directo desde Empleados o impórtalo con la columna{' '}
            <code className="font-mono text-foreground">jefe</code>.
        </EmptyState>
    );
}
