import { Link } from '@inertiajs/react';
import { CircleCheck, Network } from 'lucide-react';
import EmptyState from '@/components/humetria/empty-state';
import { Button } from '@/components/ui/button';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { index } from '@/routes/tenant/org-chart';

/** Nothing to fix: everyone has a manager who is still here, and one person at the top at most. */
export default function OrgQualityEmpty() {
    const tenantSlug = useTenantSlug();

    return (
        <EmptyState
            icon={CircleCheck}
            title="La línea de reporte está completa."
            action={
                <Button asChild>
                    <Link href={index(tenantSlug)}>
                        <Network />
                        Ver organigrama
                    </Link>
                </Button>
            }
        >
            Nadie está sin jefe ni reporta a alguien dado de baja. Lo que una
            carga deje pendiente aparecerá aquí.
        </EmptyState>
    );
}
