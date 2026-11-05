import { Link } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { create } from '@/routes/tenant/compensation/scenarios';

export default function NewScenarioButton() {
    const tenantSlug = useTenantSlug();

    return (
        <Button asChild>
            <Link href={create(tenantSlug)}>
                <Plus />
                Nuevo escenario
            </Link>
        </Button>
    );
}
