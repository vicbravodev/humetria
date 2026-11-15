import { Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { trend } from '@/routes/tenant/pulse';

/** From the home of the pulse to how the company moves from survey to survey: the primary action of the home. */
export default function TrendLink() {
    const tenantSlug = useTenantSlug();

    return (
        <Button asChild>
            <Link href={trend(tenantSlug)}>Ver tendencia</Link>
        </Button>
    );
}
