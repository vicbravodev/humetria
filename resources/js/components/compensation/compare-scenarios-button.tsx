import { Link } from '@inertiajs/react';
import { GitCompareArrows } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { compare } from '@/routes/tenant/compensation/scenarios';

/** Opens the comparison of the ticked scenarios; until two are ticked it says why it cannot. */
export default function CompareScenariosButton({
    chosen,
    ready,
}: {
    chosen: string[];
    ready: boolean;
}) {
    const tenantSlug = useTenantSlug();

    return ready ? (
        <Button variant="outline" asChild>
            <Link href={compare(tenantSlug, { query: { ids: chosen } })}>
                <GitCompareArrows />
                Comparar {chosen.length}
            </Link>
        </Button>
    ) : (
        <Button
            variant="outline"
            disabled
            title="Marca de dos a cuatro escenarios para compararlos"
        >
            <GitCompareArrows />
            Comparar
        </Button>
    );
}
