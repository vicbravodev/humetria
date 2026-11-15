import { Link } from '@inertiajs/react';
import { Scale } from 'lucide-react';
import EmptyState from '@/components/humetria/empty-state';
import { Button } from '@/components/ui/button';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { create } from '@/routes/tenant/vacations/policies';

/** A company with no policy of its own follows the law as it is; whoever manages vacations can create the first one. */
export default function PolicyFirst({ canManage }: { canManage: boolean }) {
    const tenantSlug = useTenantSlug();

    return (
        <EmptyState
            icon={Scale}
            title="Tu empresa sigue la ley tal cual"
            action={
                canManage ? (
                    <Button asChild>
                        <Link href={create(tenantSlug)}>
                            Crear la primera política
                        </Link>
                    </Button>
                ) : undefined
            }
        >
            Cuando des más días o más prima que la ley, crea una política y
            compárala aquí.
        </EmptyState>
    );
}
