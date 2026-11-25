import { Link } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { useIsQuick, useScenarioRoutes } from '@/hooks/use-scenario-routes';

export default function NewScenarioButton() {
    const tenantSlug = useTenantSlug();
    const { create } = useScenarioRoutes();
    const quick = useIsQuick();

    return (
        <Button asChild>
            <Link href={create(tenantSlug)}>
                <Plus />
                {quick ? 'Nueva cotización' : 'Nuevo escenario'}
            </Link>
        </Button>
    );
}
