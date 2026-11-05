import { Link, usePage } from '@inertiajs/react';
import { Building2 } from 'lucide-react';
import EmptyState from '@/components/humetria/empty-state';
import { Button } from '@/components/ui/button';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { index as workCenters } from '@/routes/tenant/work-centers';

/** "Empresa nueva, sin centros" (board V3-Estados): every route hangs from a work center, so the panel starts there. */
export default function DashboardNoCenters() {
    const { tenant } = usePage().props;
    const tenantSlug = useTenantSlug();

    return (
        <EmptyState
            icon={Building2}
            title="Empieza por tu primer centro de trabajo"
            action={
                tenant?.can.manage_organization === true ? (
                    <Button asChild>
                        <Link href={workCenters(tenantSlug)}>
                            Agregar centro
                        </Link>
                    </Button>
                ) : undefined
            }
        >
            Una planta, oficina o tienda con domicilio propio. Con eso sabemos
            qué normas te tocan.
        </EmptyState>
    );
}
